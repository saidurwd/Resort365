<?php

/*
| Front desk & check-in (Step 2.2). "Done when": a demo booking is checked in from the dashboard and
| appears as in-house.
*/

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Modules\Billing\Contracts\FolioPostingContract;
use Modules\Billing\DTOs\FolioCharge;
use Modules\Billing\Enums\PaymentType;
use Modules\Billing\Models\ChargeCode;
use Modules\Billing\Models\Payment;
use Modules\Billing\Services\DefaultChargeCodes;
use Modules\FrontOffice\Events\GuestCheckedIn;
use Modules\Guest\Models\Guest;
use Modules\Property\Models\Property;
use Modules\Reservation\Actions\ApplyPayment;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Models\InventoryLock;
use Modules\Reservation\Models\Reservation;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

require_once __DIR__.'/../../../Reservation/tests/Support/management-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    Notification::fake();
});

function businessDate(): CarbonImmutable
{
    return CarbonImmutable::parse(booking(fn (): string => Property::query()->where('code', 'CXB')->sole()->business_date->toDateString()));
}

/**
 * A booking arriving on the business date for two nights, its deposit paid (Confirmed).
 *
 * @param  list<string>  $units
 */
function arrivalToday(array $units = ['401']): Reservation
{
    $reservation = bookStay($units, businessDate()->toDateString(), businessDate()->addDays(2)->toDateString());
    booking(fn () => ApplyPayment::make()->handle($reservation->id, $reservation->deposit_required, $reservation->deposit_required));

    return freshReservation($reservation->id);
}

it('checks a booking in from the dashboard and shows it in house', function (): void {
    Event::fake([GuestCheckedIn::class]);
    staffUser();
    $reservation = arrivalToday();

    get(tenantUrl('sunrise', '/frontoffice'))->assertOk()
        ->assertSeeHtml('data-list="arrivals"')->assertSeeHtml('data-check-in="'.$reservation->code.'"')->assertSee('Nobody is checked in.');

    get(tenantUrl('sunrise', "/frontoffice/check-in/{$reservation->id}"))->assertOk()->assertSee('Room 401')->assertSeeHtml('data-check-in-form');
    post(tenantUrl('sunrise', "/frontoffice/check-in/{$reservation->id}"))->assertRedirect(tenantUrl('sunrise', '/frontoffice'))->assertSessionHas('success');

    $checkedIn = freshReservation($reservation->id);
    expect($checkedIn->status)->toBe(ReservationStatus::CheckedIn)
        ->and($checkedIn->items->pluck('status')->unique()->all())->toBe([ReservationStatus::CheckedIn])
        ->and($checkedIn->checked_in_at)->not->toBeNull();

    get(tenantUrl('sunrise', '/frontoffice'))->assertOk()
        ->assertSeeHtml('data-list="in-house"')->assertDontSee('Nobody is checked in.')->assertSee('No more arrivals today.');
    Event::assertDispatched(GuestCheckedIn::class, fn (GuestCheckedIn $event): bool => $event->reservationId === $reservation->id && $event->roomIds === [bookingIds()['rooms']['401']]);
});

it('lets other modules charge the guest once checked in', function (): void {
    booking(fn () => app(DefaultChargeCodes::class)->ensure());
    staffUser();
    $reservation = arrivalToday();
    post(tenantUrl('sunrise', "/frontoffice/check-in/{$reservation->id}"));

    $posting = booking(fn () => app(FolioPostingContract::class)->postCharge(new FolioCharge($reservation->id, (int) ChargeCode::query()->where('code', 'FNB')->value('id'), '500.00')));
    expect($posting->total)->toBe('500.00');
});

it('refuses to check in a tentative booking or one arriving later', function (): void {
    staffUser();
    $tentative = bookStay(['401'], businessDate()->toDateString(), businessDate()->addDay()->toDateString());
    $later = bookStay(['402'], businessDate()->addDays(3)->toDateString(), businessDate()->addDays(4)->toDateString());
    booking(fn () => ApplyPayment::make()->handle($later->id, $later->deposit_required, $later->deposit_required));

    get(tenantUrl('sunrise', "/frontoffice/check-in/{$tentative->id}"))->assertOk()->assertSeeHtml('data-not-ready');
    post(tenantUrl('sunrise', "/frontoffice/check-in/{$tentative->id}"))->assertSessionHas('error');
    post(tenantUrl('sunrise', "/frontoffice/check-in/{$later->id}"))->assertSessionHas('error');

    expect(freshReservation($tentative->id)->status)->toBe(ReservationStatus::Tentative)
        ->and(freshReservation($later->id)->status)->toBe(ReservationStatus::Confirmed);
});

it('changes the room to another of the same type at the same price, refusing a taken room', function (): void {
    staffUser();
    $reservation = arrivalToday(['401']);
    $ids = bookingIds();
    $item = $reservation->items->sole();

    post(tenantUrl('sunrise', "/frontoffice/check-in/{$reservation->id}/room"), ['reservation_item_id' => $item->id, 'room_id' => $ids['rooms']['402']])->assertSessionHas('success');

    $moved = freshReservation($reservation->id);
    expect($moved->items->sole()->room_id)->toBe($ids['rooms']['402'])
        ->and($moved->grand_total)->toBe($reservation->grand_total)
        ->and(booking(fn (): array => InventoryLock::query()->where('reservation_id', $reservation->id)->pluck('room_id')->unique()->values()->all()))->toBe([$ids['rooms']['402']]);

    bookStay(['401'], businessDate()->toDateString(), businessDate()->addDay()->toDateString());
    post(tenantUrl('sunrise', "/frontoffice/check-in/{$reservation->id}/room"), ['reservation_item_id' => $item->id, 'room_id' => $ids['rooms']['401']])->assertSessionHas('error');
    expect(freshReservation($reservation->id)->items->sole()->room_id)->toBe($ids['rooms']['402']);
});

it('records the guest\'s ID and keeps the scan with the guest', function (): void {
    Storage::fake('attachments');
    staffUser();
    $reservation = arrivalToday();

    post(tenantUrl('sunrise', "/frontoffice/check-in/{$reservation->id}/identity"), [
        'id_type' => 'passport', 'id_number' => 'BX1234567', 'id_expiry' => '2030-05-31', 'nationality_code' => 'bd',
        'scan' => UploadedFile::fake()->image('passport.jpg'),
    ])->assertSessionHas('success');

    $guest = booking(fn (): Guest => Guest::query()->findOrFail($reservation->primary_guest_id));
    expect([$guest->id_type?->value, $guest->id_number, $guest->id_expiry?->toDateString(), $guest->nationality_code])->toBe(['passport', 'BX1234567', '2030-05-31', 'BD'])
        ->and($guest->id_number_hash)->not->toBeNull()
        ->and($guest->phone)->toBe('+8801711000001')
        ->and(booking(fn (): int => $guest->getMedia('attachments')->count()))->toBe(1);

    get(tenantUrl('sunrise', "/frontoffice/check-in/{$reservation->id}"))->assertSeeHtml('data-id-on-file');
    post(tenantUrl('sunrise', "/frontoffice/check-in/{$reservation->id}/identity"), ['id_type' => 'passport', 'id_number' => ''])->assertSessionHasErrors('id_number');
});

it('takes a security deposit without touching the booking\'s balance, and returns to the check-in screen', function (): void {
    staffUser();
    $reservation = arrivalToday();
    $back = tenantUrl('sunrise', "/frontoffice/check-in/{$reservation->id}");

    post(tenantUrl('sunrise', '/billing/payments'), ['reservation_id' => $reservation->id, 'method' => 'cash', 'amount' => '5000', 'security_deposit' => 1, 'return_to' => $back])
        ->assertRedirect($back)->assertSessionHas('success');

    $deposit = booking(fn (): Payment => Payment::query()->where('payment_type', PaymentType::SecurityDeposit->value)->sole());
    expect($deposit->amount)->toBe('5000.00')
        ->and($deposit->folio_id)->toBeNull()
        ->and(freshReservation($reservation->id)->amount_paid)->toBe($reservation->amount_paid);

    // A return address elsewhere is ignored.
    post(tenantUrl('sunrise', '/billing/payments'), ['reservation_id' => $reservation->id, 'method' => 'cash', 'amount' => '10', 'return_to' => 'https://evil.example/x'])
        ->assertRedirect(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}#payments"));
});

it('prints the registration card and shows the front desk tab', function (): void {
    staffUser();
    $reservation = arrivalToday();

    get(tenantUrl('sunrise', "/frontoffice/check-in/{$reservation->id}/card"))->assertOk()->assertSeeHtml('data-registration-card')->assertSee($reservation->code)->assertSee('Guest signature');
    get(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}"))->assertOk()->assertSeeHtml('data-tab-hash="frontdesk"')->assertSeeHtml('data-tab-check-in');
});

it('starts a walk-in booking for tonight from the dashboard', function (): void {
    staffUser();

    get(tenantUrl('sunrise', '/frontoffice'))->assertSeeHtml('data-walk-in');
    get(tenantUrl('sunrise', '/reservation/bookings/new?walk_in=1'))->assertOk()
        ->assertSee(businessDate()->toDateString())->assertSee(businessDate()->addDay()->toDateString());

    expect(session('reservation.booking_wizard.source'))->toBe('walk_in');
});

it('keeps the desk and check-in to front-office staff', function (): void {
    $reservation = arrivalToday();

    staffUser(DefaultRole::HousekeepingSupervisor);
    get(tenantUrl('sunrise', '/frontoffice'))->assertForbidden();

    staffUser(DefaultRole::ReservationAgent);
    get(tenantUrl('sunrise', '/frontoffice'))->assertOk();
    get(tenantUrl('sunrise', "/frontoffice/check-in/{$reservation->id}"))->assertForbidden();
    post(tenantUrl('sunrise', "/frontoffice/check-in/{$reservation->id}"))->assertForbidden();
});

it('does not open another tenant\'s booking', function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'greenvalley'])));
    $theirs = bookStay(['401'], slug: 'greenvalley');
    staffUser();

    get(tenantUrl('sunrise', "/frontoffice/check-in/{$theirs->id}"))->assertNotFound();
});
