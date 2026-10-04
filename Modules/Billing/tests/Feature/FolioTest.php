<?php

/*
| Folios & charges (Step 2.1). "Done when": extras can be posted to and voided from a folio; the
| FolioPostingContract rejects a charge for a guest who is not checked in.
|
| Charge codes use the ROOM tax category of booking-setup: SC 10% then VAT 15% (1,000 → 1,265.00).
*/

use App\Actions\Tenancy\CreateTenant;
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Billing\Actions\OpenFolio;
use Modules\Billing\Actions\PostCharge;
use Modules\Billing\Actions\RecordPayment;
use Modules\Billing\Actions\SaveRoutingRule;
use Modules\Billing\Contracts\FolioPostingContract;
use Modules\Billing\DTOs\FolioCharge;
use Modules\Billing\DTOs\NewPayment;
use Modules\Billing\Enums\BillTo;
use Modules\Billing\Enums\ChargeCategory;
use Modules\Billing\Enums\FolioLineType;
use Modules\Billing\Enums\FolioStatus;
use Modules\Billing\Enums\FolioType;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Exceptions\ChargeRejected;
use Modules\Billing\Models\ChargeCode;
use Modules\Billing\Models\ExtraService;
use Modules\Billing\Models\Folio;
use Modules\Billing\Models\FolioLine;
use Modules\Billing\Services\DefaultChargeCodes;
use Modules\Core\Contracts\Settings;
use Modules\Core\Models\TaxCategory;
use Modules\Guest\Models\Company;
use Modules\Property\Models\Property;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Models\Reservation;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

require_once __DIR__.'/../../../Reservation/tests/Support/management-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    booking(fn () => app(DefaultChargeCodes::class)->ensure(TaxCategory::query()->where('code', 'ROOM')->value('id')));
    Notification::fake();
});

function code(string $code): int
{
    return booking(fn (): int => (int) ChargeCode::query()->where('code', $code)->value('id'));
}

function guestFolioOf(int $reservationId): Folio
{
    return booking(fn (): Folio => Folio::query()->with('lines')->where('reservation_id', $reservationId)->where('type', FolioType::Guest->value)->sole());
}

function checkIn(Reservation $reservation): void
{
    booking(fn () => Reservation::query()->whereKey($reservation->id)->update(['status' => ReservationStatus::CheckedIn->value]));
}

it('opens a guest folio with every new booking', function (): void {
    $reservation = bookStay(['401']);
    $folio = guestFolioOf($reservation->id);

    expect($folio->folio_no)->toStartWith('FOL-')
        ->and([$folio->status, $folio->bill_to_type, $folio->balance, $folio->currency_code])->toBe([FolioStatus::Open, BillTo::Guest, '0.00', 'BDT'])
        ->and($folio->name)->toContain('Rahim Uddin');
});

it('posts an extra to the folio and voids it, keeping the balance right', function (): void {
    staffUser(DefaultRole::FrontOfficeManager);
    $reservation = bookStay(['401']);
    $folio = guestFolioOf($reservation->id);
    $pickup = booking(fn (): ExtraService => ExtraService::factory()->create(['name' => 'Airport pickup', 'charge_code_id' => code('TRANSFER'), 'unit_price' => '1000.00']));

    get(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}"))->assertOk()->assertSeeHtml('data-tab-hash="folios"')->assertSeeHtml('data-folio="'.$folio->folio_no.'"');

    post(tenantUrl('sunrise', "/billing/folios/{$folio->id}/charges"), ['extra_service_id' => $pickup->id, 'quantity' => 2])
        ->assertRedirect(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}#folios"))->assertSessionHas('success');

    $line = booking(fn (): FolioLine => FolioLine::query()->sole());
    expect([$line->description, $line->quantity, $line->amount, $line->tax_amount, $line->total])->toBe(['Airport pickup', '2.00', '2000.00', '530.00', '2530.00'])
        ->and($line->extra_service_id)->toBe($pickup->id)
        // Posted on the property's business date (its local date, not the UTC date).
        ->and($line->posting_date->toDateString())->toBe(booking(fn (): string => Property::query()->where('code', 'CXB')->sole()->business_date->toDateString()))
        ->and(guestFolioOf($reservation->id)->balance)->toBe('2530.00');

    post(tenantUrl('sunrise', "/billing/folios/{$folio->id}/void"), ['folio_line_id' => $line->id, 'reason' => 'Guest cancelled the pickup'])->assertSessionHas('success');

    $voided = booking(fn (): FolioLine => FolioLine::query()->sole());
    expect([$voided->is_voided, $voided->void_reason])->toBe([true, 'Guest cancelled the pickup'])
        ->and($voided->voided_by)->not->toBeNull()
        ->and(guestFolioOf($reservation->id)->balance)->toBe('0.00');
});

it('rejects a charge from another module for a guest who is not checked in', function (): void {
    $reservation = bookStay(['401']);

    expect(fn () => booking(fn () => app(FolioPostingContract::class)->postCharge(new FolioCharge($reservation->id, code('FNB'), '1000.00'))))
        ->toThrow(ChargeRejected::class, 'not checked in')
        ->and(booking(fn (): int => FolioLine::query()->count()))->toBe(0);

    checkIn($reservation);
    $posting = booking(fn () => app(FolioPostingContract::class)->postCharge(new FolioCharge($reservation->id, code('FNB'), '1000.00',
        description: 'Restaurant bill R-17', referenceType: 'restaurant_bill', referenceId: 17, revenuePostedBySource: true)));

    $line = booking(fn (): FolioLine => FolioLine::query()->findOrFail($posting->lineId));
    expect([$posting->total, $posting->folioBalance])->toBe(['1265.00', '1265.00'])
        ->and([$line->reference_type, $line->reference_id, $line->revenue_posted_by_source])->toBe(['restaurant_bill', 17, true]);
});

it('rejects contract charges on a closed folio or over the credit limit', function (): void {
    $reservation = bookStay(['401']);
    checkIn($reservation);
    booking(fn () => app(Settings::class)->set('billing.guest_credit_limit', '2000', bookingIds()['property']));
    $contract = fn (string $price) => booking(fn () => app(FolioPostingContract::class)->postCharge(new FolioCharge($reservation->id, code('FNB'), $price)));

    $contract('1000.00'); // 1,265.00
    expect(fn () => $contract('1000.00'))->toThrow(ChargeRejected::class, 'credit limit');

    booking(fn () => Folio::query()->where('reservation_id', $reservation->id)->update(['status' => FolioStatus::Closed->value]));
    expect(fn () => $contract('10.00'))->toThrow(ChargeRejected::class, 'closed');
});

it('routes room charges to the company folio and keeps food on the guest folio', function (): void {
    $reservation = bookStay(['401']);
    $company = booking(fn (): Company => Company::factory()->create(['name' => 'Acme Travels Ltd', 'credit_limit' => '100000']));
    $companyFolio = booking(fn (): Folio => OpenFolio::make()->handle($reservation->id, FolioType::Company, BillTo::Company, $company->id));
    booking(fn () => SaveRoutingRule::make()->handle($reservation->id, ChargeCategory::Room, $companyFolio));

    $room = booking(fn (): FolioLine => PostCharge::make()->handle(new FolioCharge($reservation->id, code('ROOM'), '6000.00')));
    $food = booking(fn (): FolioLine => PostCharge::make()->handle(new FolioCharge($reservation->id, code('FNB'), '800.00')));

    expect($room->folio_id)->toBe($companyFolio->id)
        ->and($room->routed_from_folio_id)->toBe(guestFolioOf($reservation->id)->id)
        ->and($food->folio_id)->toBe(guestFolioOf($reservation->id)->id)
        ->and($companyFolio->name)->toBe('Acme Travels Ltd')
        ->and(booking(fn (): string => Folio::query()->findOrFail($companyFolio->id)->balance))->toBe('7590.00');
});

it('shows payments on the guest folio', function (): void {
    $reservation = bookStay(['401']);
    booking(fn () => RecordPayment::make()->handle(new NewPayment($reservation->id, PaymentMethod::Cash, '6831.00')));

    $folio = guestFolioOf($reservation->id);
    expect($folio->balance)->toBe('-6831.00')
        ->and($folio->lines->sole()->line_type)->toBe(FolioLineType::Payment);
});

it('posts adjustments with a reason, for managers only', function (): void {
    $reservation = bookStay(['401']);
    $folio = guestFolioOf($reservation->id);

    staffUser(DefaultRole::FrontDeskAgent);
    post(tenantUrl('sunrise', "/billing/folios/{$folio->id}/adjustments"), ['amount' => '-500', 'reason' => 'Goodwill'])->assertForbidden();
    post(tenantUrl('sunrise', "/billing/folios/{$folio->id}/void"), ['folio_line_id' => 1, 'reason' => 'x'])->assertForbidden();

    staffUser(DefaultRole::FrontOfficeManager);
    post(tenantUrl('sunrise', "/billing/folios/{$folio->id}/adjustments"), ['amount' => '-500', 'reason' => ''])->assertSessionHasErrors('reason');
    post(tenantUrl('sunrise', "/billing/folios/{$folio->id}/adjustments"), ['amount' => '-500', 'reason' => 'Goodwill: noisy room'])->assertSessionHas('success');

    expect(guestFolioOf($reservation->id)->balance)->toBe('-500.00');
});

it('refuses voiding payments and charging a cancelled booking', function (): void {
    $reservation = bookStay(['401']);
    booking(fn () => RecordPayment::make()->handle(new NewPayment($reservation->id, PaymentMethod::Cash, '100.00')));
    $payment = guestFolioOf($reservation->id)->lines->sole();

    staffUser(DefaultRole::FrontOfficeManager);
    post(tenantUrl('sunrise', '/billing/folios/'.guestFolioOf($reservation->id)->id.'/void'), ['folio_line_id' => $payment->id, 'reason' => 'oops'])->assertSessionHas('error');

    booking(fn () => Reservation::query()->whereKey($reservation->id)->update(['status' => ReservationStatus::Cancelled->value]));
    expect(fn () => booking(fn () => PostCharge::make()->handle(new FolioCharge($reservation->id, code('MISC'), '10.00'))))->toThrow(ChargeRejected::class, 'cancelled');
});

it('validates a charge from the tab', function (): void {
    staffUser();
    $folio = guestFolioOf(bookStay(['401'])->id);

    post(tenantUrl('sunrise', "/billing/folios/{$folio->id}/charges"), ['quantity' => 0])->assertSessionHasErrors(['charge_code_id', 'unit_price', 'quantity']);
});

it('opens a company folio from the tab', function (): void {
    staffUser();
    $reservation = bookStay(['401']);
    $company = booking(fn (): Company => Company::factory()->create(['name' => 'Acme Travels Ltd']));

    post(tenantUrl('sunrise', '/billing/folios'), ['reservation_id' => $reservation->id, 'type' => 'company', 'bill_to_type' => 'company', 'company_id' => $company->id])
        ->assertSessionHas('success');

    expect(booking(fn (): array => Folio::query()->where('reservation_id', $reservation->id)->pluck('name')->all()))->toContain('Acme Travels Ltd');
});

it('gives a new tenant the default charge codes', function (): void {
    $tenant = app(CreateTenant::class)->handle('bluebay', 'Blue Bay Resort');

    expect(app(TenantContext::class)->run($tenant, fn (): array => ChargeCode::query()->orderBy('sort_order')->pluck('code')->all()))
        ->toBe(['ROOM', 'EXBED', 'FNB', 'TRANSFER', 'LAUNDRY', 'SPA', 'MISC']);
});

describe('setup', function (): void {
    it('manages charge codes and extras', function (): void {
        staffUser(DefaultRole::GeneralManager);

        get(tenantUrl('sunrise', '/billing/charge-codes'))->assertOk()->assertSeeHtml('data-code="ROOM"');
        post(tenantUrl('sunrise', '/billing/charge-codes'), ['code' => 'boat', 'name' => 'Boat trip', 'category' => 'extra', 'is_active' => 1])->assertSessionHas('success');
        post(tenantUrl('sunrise', '/billing/charge-codes'), ['code' => 'BOAT', 'name' => 'Again', 'category' => 'extra'])->assertSessionHasErrors('code');

        post(tenantUrl('sunrise', '/billing/extras'), ['name' => 'Sunset boat trip', 'charge_code_id' => code('BOAT'), 'unit_price' => '2500', 'unit' => 'person', 'is_active' => 1])
            ->assertSessionHas('success');
        get(tenantUrl('sunrise', '/billing/extras'))->assertOk()->assertSeeHtml('data-extra="Sunset boat trip"');
    });

    it('keeps setup to managers', function (): void {
        staffUser(DefaultRole::FrontDeskAgent);

        get(tenantUrl('sunrise', '/billing/charge-codes'))->assertForbidden();
        post(tenantUrl('sunrise', '/billing/extras'), ['name' => 'X'])->assertForbidden();
    });
});
