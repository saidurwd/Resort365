<?php

/*
| Stay changes & group bookings (Step 2.4). "Done when": moving an in-house guest to another cottage
| frees the old room for the remaining nights; a 6-room group checks in room by room.
|
| Rooms 401/402 (cottage C04), 701–703 (C07), 801–803 (C08): 6,000 a night + SC 10% + VAT 15% = 7,590.00.
*/

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Modules\Billing\Enums\ChargeCategory;
use Modules\Billing\Enums\FolioType;
use Modules\Billing\Models\Folio;
use Modules\Billing\Models\FolioRoutingRule;
use Modules\FrontOffice\Events\GuestCheckedIn;
use Modules\Guest\Models\Guest;
use Modules\Property\Models\Property;
use Modules\Reservation\Contracts\StayOperations;
use Modules\Reservation\DTOs\AvailabilitySearch;
use Modules\Reservation\DTOs\Occupancy;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Models\InventoryLock;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationGuest;
use Modules\Reservation\Services\AvailabilityService;

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

require_once __DIR__.'/../../../Reservation/tests/Support/management-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    Notification::fake();
});

function tonight(): CarbonImmutable
{
    return CarbonImmutable::parse(booking(fn (): string => Property::query()->where('code', 'CXB')->sole()->business_date->toDateString()));
}

/**
 * In house: arrived yesterday, leaving in two days (nights: yesterday, tonight, tomorrow).
 *
 * @param  list<string>  $units
 * @param  array<string, mixed>  $overrides
 */
function stayingGuest(array $units = ['401'], array $overrides = []): Reservation
{
    $reservation = bookStay($units, tonight()->subDay()->toDateString(), tonight()->addDays(2)->toDateString(), ['depositPercent' => '0', 'allowDepositOverride' => true, ...$overrides]);
    booking(fn () => app(StayOperations::class)->checkIn($reservation->id));

    return freshReservation($reservation->id);
}

/**
 * @return list<string>
 */
function lockDates(int $reservationId, string $room): array
{
    return booking(fn (): array => InventoryLock::query()->where('reservation_id', $reservationId)->where('room_id', bookingIds()['rooms'][$room])
        ->orderBy('stay_date')->get()->map(fn (InventoryLock $lock): string => $lock->stay_date->toDateString())->all());
}

it('moves an in-house guest to another cottage and frees the old room from tonight', function (): void {
    staffUser();
    $reservation = stayingGuest(['401']);
    $item = $reservation->items->sole();

    get(tenantUrl('sunrise', "/frontoffice/stay/{$reservation->id}"))->assertOk()->assertSeeHtml('data-move="'.$item->id.'"');
    post(tenantUrl('sunrise', "/frontoffice/stay/{$reservation->id}/move"), ['reservation_item_id' => $item->id, 'room_id' => bookingIds()['rooms']['701']])->assertSessionHas('success');

    expect(lockDates($reservation->id, '401'))->toBe([tonight()->subDay()->toDateString()])
        ->and(lockDates($reservation->id, '701'))->toBe([tonight()->toDateString(), tonight()->addDay()->toDateString()])
        ->and(freshReservation($reservation->id)->grand_total)->toBe($reservation->grand_total);

    $ids = bookingIds();
    $search = new AvailabilitySearch($ids['property'], $ids['plan'], tonight(), tonight()->addDays(2), new Occupancy(2, 0));
    expect(booking(fn (): array => app(AvailabilityService::class)->lockedRoomIds($search)))->not->toContain($ids['rooms']['401']);
});

it('checks a 6-room group in room by room, with a master folio for the rooms', function (): void {
    Event::fake([GuestCheckedIn::class]);
    staffUser();
    $units = ['401', '402', '701', '702', '703', '801'];
    $group = bookStay($units, tonight()->toDateString(), tonight()->addDays(2)->toDateString(), ['groupName' => 'Dhaka Bank offsite', 'depositPercent' => '0', 'allowDepositOverride' => true]);

    $master = booking(fn (): Folio => Folio::query()->where('reservation_id', $group->id)->where('type', FolioType::Master->value)->sole());
    expect($master->name)->toBe('Master — Dhaka Bank offsite')
        ->and(booking(fn (): ?int => FolioRoutingRule::query()->where('reservation_id', $group->id)->where('category', ChargeCategory::Room->value)->value('target_folio_id')))->toBe($master->id);

    get(tenantUrl('sunrise', "/frontoffice/check-in/{$group->id}"))->assertOk()->assertSee('Check in all 6 rooms');

    foreach ($group->items->values() as $index => $item) {
        $response = post(tenantUrl('sunrise', "/frontoffice/check-in/{$group->id}"), ['item_id' => $item->id])->assertSessionHas('success');
        $now = freshReservation($group->id);

        expect($now->status)->toBe(ReservationStatus::CheckedIn)
            ->and($now->items->where('status', ReservationStatus::CheckedIn)->count())->toBe($index + 1);

        $index < 5
            ? $response->assertRedirect(tenantUrl('sunrise', "/frontoffice/check-in/{$group->id}"))
            : $response->assertRedirect(tenantUrl('sunrise', '/frontoffice'));
    }

    Event::assertDispatchedTimes(GuestCheckedIn::class, 6);
    Event::assertDispatched(GuestCheckedIn::class, fn (GuestCheckedIn $event): bool => count($event->roomIds) === 1);
});

it('shows a partly checked-in group on the desk with what is left', function (): void {
    staffUser();
    $group = bookStay(['401', '402'], tonight()->toDateString(), tonight()->addDay()->toDateString(), ['groupName' => 'Wedding party', 'depositPercent' => '0', 'allowDepositOverride' => true]);
    post(tenantUrl('sunrise', "/frontoffice/check-in/{$group->id}"), ['item_id' => $group->items->first()?->id]);

    get(tenantUrl('sunrise', '/frontoffice'))->assertOk()->assertSee('Wedding party')->assertSeeHtml('data-progress')->assertSee('Check in the rest');
});

it('extends a stay, pricing and locking the extra night, and refuses a clash', function (): void {
    staffUser();
    $reservation = stayingGuest(['401']);
    $newOut = tonight()->addDays(3)->toDateString();

    post(tenantUrl('sunrise', "/frontoffice/stay/{$reservation->id}/extend"), ['check_out' => $newOut])->assertSessionHas('success');
    $extended = freshReservation($reservation->id);
    expect([$extended->check_out->toDateString(), $extended->grand_total])->toBe([$newOut, bcadd($reservation->grand_total, '7590.00', 2)])
        ->and(lockDates($reservation->id, '401'))->toHaveCount(4);

    bookStay(['401'], tonight()->addDays(3)->toDateString(), tonight()->addDays(4)->toDateString());
    post(tenantUrl('sunrise', "/frontoffice/stay/{$reservation->id}/extend"), ['check_out' => tonight()->addDays(4)->toDateString()])->assertSessionHas('error');
    expect(freshReservation($reservation->id)->check_out->toDateString())->toBe($newOut);
});

it('ends a stay early on the business date', function (): void {
    staffUser();
    $reservation = stayingGuest(['401']);

    post(tenantUrl('sunrise', "/frontoffice/stay/{$reservation->id}/shorten"), ['check_out' => tonight()->toDateString()])->assertSessionHas('success');

    $short = freshReservation($reservation->id);
    expect($short->check_out->toDateString())->toBe(tonight()->toDateString())
        ->and($short->grand_total)->toBe('7590.00')
        ->and(lockDates($reservation->id, '401'))->toBe([tonight()->subDay()->toDateString()]);

    post(tenantUrl('sunrise', "/frontoffice/stay/{$reservation->id}/shorten"), ['check_out' => tonight()->subDays(5)->toDateString()])->assertSessionHas('error');
});

it('needs a manager to charge the new room\'s rate on a move', function (): void {
    $reservation = stayingGuest(['401']);
    $item = $reservation->items->sole();

    staffUser(DefaultRole::FrontDeskAgent);
    post(tenantUrl('sunrise', "/frontoffice/stay/{$reservation->id}/move"), ['reservation_item_id' => $item->id, 'room_id' => bookingIds()['rooms']['402'], 'reprice' => 1])->assertForbidden();

    staffUser(DefaultRole::FrontOfficeManager);
    post(tenantUrl('sunrise', "/frontoffice/stay/{$reservation->id}/move"), ['reservation_item_id' => $item->id, 'room_id' => bookingIds()['rooms']['402'], 'reprice' => 1])->assertSessionHas('success');
    expect(freshReservation($reservation->id)->items->sole()->room_id)->toBe(bookingIds()['rooms']['402']);
});

it('names each room of a group on the rooming list', function (): void {
    staffUser();
    $group = bookStay(['401', '402'], tonight()->addDays(5)->toDateString(), tonight()->addDays(7)->toDateString(), ['groupName' => 'Family reunion']);
    [$first, $second] = $group->items->values()->all();
    $karim = booking(fn (): Guest => Guest::factory()->create(['first_name' => 'Karim', 'last_name' => 'Ahmed']));
    $blacklisted = booking(fn (): Guest => Guest::factory()->create(['first_name' => 'Kamal', 'is_blacklisted' => true]));

    get(tenantUrl('sunrise', "/reservation/bookings/{$group->id}/rooming-list"))->assertOk()->assertSeeHtml('data-rooming-row="'.$first->id.'"');
    put(tenantUrl('sunrise', "/reservation/bookings/{$group->id}/rooming-list"), ['rows' => [$first->id => ['guest_id' => $karim->id], $second->id => ['new_name' => 'Sadia Rahman']]])
        ->assertRedirect(tenantUrl('sunrise', "/reservation/bookings/{$group->id}#guests"));

    $rows = booking(fn () => ReservationGuest::query()->where('reservation_id', $group->id)->whereNotNull('reservation_item_id')->get());
    expect($rows)->toHaveCount(2)
        ->and(booking(fn (): string => Guest::query()->findOrFail($rows->firstWhere('reservation_item_id', $second->id)?->guest_id)->first_name))->toBe('Sadia');

    put(tenantUrl('sunrise', "/reservation/bookings/{$group->id}/rooming-list"), ['rows' => [$first->id => ['guest_id' => $blacklisted->id]]])->assertSessionHas('error');
});

it('keeps stay changes to front-office staff', function (): void {
    $reservation = stayingGuest(['401']);
    staffUser(DefaultRole::HousekeepingSupervisor);

    get(tenantUrl('sunrise', "/frontoffice/stay/{$reservation->id}"))->assertForbidden();
});
