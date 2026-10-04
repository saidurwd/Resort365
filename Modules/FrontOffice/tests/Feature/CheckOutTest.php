<?php

/*
| Check-out & invoices (Step 2.3). "Done when": check-out produces a correct invoice with the deposit
| deducted; the balance can be moved to a company's city ledger.
|
| Room 401: 6,000 a night + SC 10% + VAT 15% = 7,590.00 a night; two nights = 15,180.00, deposit 30% = 4,554.00.
*/

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Modules\Billing\Actions\OpenFolio;
use Modules\Billing\Actions\PostCharge;
use Modules\Billing\Actions\RecordPayment;
use Modules\Billing\Actions\SaveRoutingRule;
use Modules\Billing\DTOs\FolioCharge;
use Modules\Billing\DTOs\NewPayment;
use Modules\Billing\Enums\BillTo;
use Modules\Billing\Enums\ChargeCategory;
use Modules\Billing\Enums\FolioStatus;
use Modules\Billing\Enums\FolioType;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Models\ChargeCode;
use Modules\Billing\Models\CityLedgerEntry;
use Modules\Billing\Models\Folio;
use Modules\Billing\Models\FolioLine;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Services\DefaultChargeCodes;
use Modules\Core\Models\TaxCategory;
use Modules\FrontOffice\Actions\PostStayCharges;
use Modules\FrontOffice\Events\GuestCheckedOut;
use Modules\Guest\Models\Company;
use Modules\Property\Models\Property;
use Modules\Reservation\Contracts\StayOperations;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Models\InventoryLock;
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

function departureDay(): CarbonImmutable
{
    return CarbonImmutable::parse(booking(fn (): string => Property::query()->where('code', 'CXB')->sole()->business_date->toDateString()));
}

/**
 * A guest in house in room 401 for the two nights before today (leaving today), deposit paid.
 */
function inHouse(): Reservation
{
    $reservation = bookStay(['401'], departureDay()->subDays(2)->toDateString(), departureDay()->toDateString(), ['depositPercent' => '30', 'allowDepositOverride' => true]);
    booking(fn () => RecordPayment::make()->handle(new NewPayment($reservation->id, PaymentMethod::Card, $reservation->deposit_required)));
    booking(fn () => app(StayOperations::class)->checkIn($reservation->id));

    return freshReservation($reservation->id);
}

function folioOf(int $reservationId, FolioType $type = FolioType::Guest): Folio
{
    return booking(fn (): Folio => Folio::query()->where('reservation_id', $reservationId)->where('type', $type->value)->firstOrFail());
}

function codeId(string $code): int
{
    return booking(fn (): int => (int) ChargeCode::query()->where('code', $code)->value('id'));
}

it('checks out with an invoice that deducts the deposit', function (): void {
    Event::fake([GuestCheckedOut::class]);
    staffUser();
    $reservation = inHouse();
    $deposit = $reservation->deposit_required;
    booking(fn () => PostCharge::make()->handle(new FolioCharge($reservation->id, codeId('LAUNDRY'), '500.00', description: 'Laundry')));

    get(tenantUrl('sunrise', "/frontoffice/check-out/{$reservation->id}"))->assertOk()->assertSeeHtml('data-post-charges')->assertSeeHtml('data-not-settled');
    post(tenantUrl('sunrise', "/frontoffice/check-out/{$reservation->id}/charges"))->assertSessionHas('success');

    // 15,180.00 rooms + 632.50 laundry − deposit.
    $owed = bcsub('15812.50', $deposit, 2);
    expect(folioOf($reservation->id)->balance)->toBe($owed);

    // Split payment: cash then card.
    $back = tenantUrl('sunrise', "/frontoffice/check-out/{$reservation->id}");
    $folioId = folioOf($reservation->id)->id;
    post(tenantUrl('sunrise', '/billing/payments'), ['reservation_id' => $reservation->id, 'folio_id' => $folioId, 'method' => 'cash', 'amount' => '5000', 'return_to' => $back])->assertRedirect($back);
    post(tenantUrl('sunrise', '/billing/payments'), ['reservation_id' => $reservation->id, 'folio_id' => $folioId, 'method' => 'card', 'amount' => bcsub($owed, '5000', 2), 'return_to' => $back])->assertRedirect($back);
    expect(folioOf($reservation->id)->balance)->toBe('0.00');

    post(tenantUrl('sunrise', "/frontoffice/check-out/{$reservation->id}"))->assertRedirect(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}#folios"))->assertSessionHas('success');

    $invoice = booking(fn (): Invoice => Invoice::query()->with('lines')->sole());
    expect($invoice->invoice_no)->toStartWith('INV-')
        ->and([$invoice->subtotal, $invoice->tax_total, $invoice->total, $invoice->paid, $invoice->on_account, $invoice->balance()])
        ->toBe(['12500.00', '3312.50', '15812.50', '15812.50', '0.00', '0.00'])
        ->and($invoice->tax_breakdown)->not->toBeEmpty()
        ->and(array_reduce($invoice->tax_breakdown ?? [], fn (string $sum, string $amount): string => bcadd($sum, $amount, 2), '0.00'))->toBe('3312.50')
        ->and($invoice->lines->pluck('description')->all())->toHaveCount(3)
        ->and(folioOf($reservation->id)->status)->toBe(FolioStatus::Settled)
        ->and(freshReservation($reservation->id)->status)->toBe(ReservationStatus::CheckedOut)
        ->and(booking(fn (): int => InventoryLock::query()->where('reservation_id', $reservation->id)->count()))->toBe(0);

    $pdf = get(tenantUrl('sunrise', "/billing/invoices/{$invoice->id}/pdf"))->assertOk()->assertHeader('content-type', 'application/pdf');
    expect((string) $pdf->getContent())->toStartWith('%PDF');
    Event::assertDispatched(GuestCheckedOut::class, fn (GuestCheckedOut $event): bool => $event->reservationId === $reservation->id && $event->invoiceIds === [$invoice->id]);
});

it('moves a company\'s balance to the city ledger and invoices it on account', function (): void {
    staffUser(DefaultRole::FrontOfficeManager);
    $reservation = inHouse();
    $company = booking(fn (): Company => Company::factory()->create(['name' => 'Acme Travels Ltd', 'credit_limit' => '0', 'payment_terms_days' => 30]));
    $companyFolio = booking(fn (): Folio => OpenFolio::make()->handle($reservation->id, FolioType::Company, BillTo::Company, $company->id));
    booking(fn () => SaveRoutingRule::make()->handle($reservation->id, ChargeCategory::Room, $companyFolio));
    booking(fn () => PostStayCharges::make()->handle($reservation->id));

    expect(folioOf($reservation->id, FolioType::Company)->balance)->toBe('15180.00')
        ->and(folioOf($reservation->id)->balance)->toBe('-'.$reservation->deposit_required);

    $back = tenantUrl('sunrise', "/frontoffice/check-out/{$reservation->id}");
    post(tenantUrl('sunrise', "/billing/folios/{$companyFolio->id}/city-ledger"), ['company_id' => $company->id, 'return_to' => $back])->assertRedirect($back)->assertSessionHas('success');

    // The guest's deposit is now a credit on the guest folio: refund it.
    post(tenantUrl('sunrise', '/billing/refunds'), ['reservation_id' => $reservation->id, 'kind' => 'overpayment', 'source_id' => folioOf($reservation->id)->id,
        'method' => 'card', 'amount' => $reservation->deposit_required, 'reason' => 'Company pays the room'])->assertSessionHas('success');

    post(tenantUrl('sunrise', "/frontoffice/check-out/{$reservation->id}"))->assertSessionHas('success');

    $entry = booking(fn (): CityLedgerEntry => CityLedgerEntry::query()->sole());
    $invoice = booking(fn (): Invoice => Invoice::query()->where('folio_id', $companyFolio->id)->sole());
    expect([$entry->amount, $entry->open(), $entry->invoice_id, $entry->due_on->toDateString()])->toBe(['15180.00', '15180.00', $invoice->id, departureDay()->addDays(30)->toDateString()])
        ->and([$invoice->total, $invoice->on_account, $invoice->paid, $invoice->bill_to_name])->toBe(['15180.00', '15180.00', '0.00', 'Acme Travels Ltd']);

    staffUser(DefaultRole::Accountant);
    get(tenantUrl('sunrise', '/billing/city-ledger'))->assertOk()->assertSeeHtml('data-company="Acme Travels Ltd"')->assertSee('15,180.00');
    post(tenantUrl('sunrise', "/billing/city-ledger/{$entry->id}/payments"), ['amount' => '15180.00', 'method' => 'bank_transfer', 'reference' => 'TT-991'])->assertSessionHas('success');
    expect(booking(fn (): string => CityLedgerEntry::query()->sole()->status->value))->toBe('paid');
});

it('posts each room night only once', function (): void {
    $reservation = inHouse();

    expect(booking(fn (): int => PostStayCharges::make()->handle($reservation->id)))->toBe(2)
        ->and(booking(fn (): int => PostStayCharges::make()->handle($reservation->id)))->toBe(0)
        ->and(booking(fn (): int => FolioLine::query()->where('reference_type', 'reservation_item_night')->count()))->toBe(2);
});

it('refuses to check out an unsettled folio or a guest due to stay longer', function (): void {
    staffUser();
    $reservation = inHouse();

    post(tenantUrl('sunrise', "/frontoffice/check-out/{$reservation->id}"))->assertSessionHas('error');
    expect(freshReservation($reservation->id)->status)->toBe(ReservationStatus::CheckedIn)
        ->and(booking(fn (): int => Invoice::query()->count()))->toBe(0);

    $staying = bookStay(['402'], departureDay()->toDateString(), departureDay()->addDays(2)->toDateString(), ['depositPercent' => '0', 'allowDepositOverride' => true]);
    booking(fn () => app(StayOperations::class)->checkIn($staying->id));
    get(tenantUrl('sunrise', "/frontoffice/check-out/{$staying->id}"))->assertOk()->assertSeeHtml('data-early');
});

it('keeps check-out to front-office staff', function (): void {
    $reservation = inHouse();
    staffUser(DefaultRole::HousekeepingSupervisor);

    get(tenantUrl('sunrise', "/frontoffice/check-out/{$reservation->id}"))->assertForbidden();
    post(tenantUrl('sunrise', "/frontoffice/check-out/{$reservation->id}"))->assertForbidden();
});
