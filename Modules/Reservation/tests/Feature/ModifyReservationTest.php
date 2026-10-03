<?php

/*
| Changing a reservation's stay (ARCHITECTURE §6.7). Step 1.7 "Done when": a date change that
| collides with another booking fails cleanly and keeps the original.
|
| Room 401: 6,000 a night for two; SC 10% then VAT 15% → 7,590.00 a night; deposit 30%.
*/

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Reservation\Actions\ApplyPayment;
use Modules\Reservation\Actions\CancelReservation;
use Modules\Reservation\Actions\ModifyReservation;
use Modules\Reservation\DTOs\BookingItem;
use Modules\Reservation\DTOs\ReservationChange;
use Modules\Reservation\Enums\ItemType;
use Modules\Reservation\Enums\ReservationLogAction;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Events\ReservationConfirmed;
use Modules\Reservation\Exceptions\ReservationNotChangeable;
use Modules\Reservation\Exceptions\RoomNoLongerAvailable;
use Modules\Reservation\Models\InventoryLock;

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

require_once __DIR__.'/../Support/management-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
});

/**
 * @param  list<string>  $units  room numbers or cottage codes
 */
function stayChange(string $checkIn, string $checkOut, array $units = ['401']): ReservationChange
{
    $ids = bookingIds();

    return new ReservationChange(CarbonImmutable::parse($checkIn), CarbonImmutable::parse($checkOut), array_map(fn (string $unit): BookingItem => isset($ids['cottages'][$unit])
        ? new BookingItem(ItemType::Cottage, $ids['cottages'][$unit], $ids['plan'], 2)
        : new BookingItem(ItemType::Room, $ids['rooms'][$unit], $ids['plan'], 2), $units));
}

/**
 * @return list<string> locked dates of a room for a reservation
 */
function lockedDates(int $reservationId, string $room): array
{
    return booking(fn (): array => InventoryLock::query()->where('reservation_id', $reservationId)->where('room_id', bookingIds()['rooms'][$room])
        ->orderBy('stay_date')->get()->map(fn (InventoryLock $lock): string => $lock->stay_date->toDateString())->all());
}

it('moves the dates: old locks released, new ones taken, price and deposit recalculated', function (): void {
    $reservation = bookStay(['401'], '2026-11-10', '2026-11-13');
    expect([$reservation->grand_total, $reservation->deposit_required])->toBe(['22770.00', '6831.00']);

    booking(fn () => ModifyReservation::make()->handle($reservation, stayChange('2026-11-12', '2026-11-14')));
    $changed = freshReservation($reservation->id);

    expect([$changed->check_in->toDateString(), $changed->check_out->toDateString()])->toBe(['2026-11-12', '2026-11-14'])
        ->and([$changed->grand_total, $changed->deposit_required, $changed->balance_due])->toBe(['15180.00', '4554.00', '15180.00'])
        ->and($changed->items)->toHaveCount(1)
        ->and(lockedDates($reservation->id, '401'))->toBe(['2026-11-12', '2026-11-13'])
        ->and($changed->logs->first()?->action)->toBe(ReservationLogAction::Modified)
        ->and($changed->logs->first()?->changes)->toMatchArray(['check_in' => ['2026-11-10', '2026-11-12'], 'grand_total' => ['22770.00', '15180.00']]);
});

it('fails cleanly when the new dates collide with another booking and keeps the original', function (): void {
    $original = bookStay(['401'], '2026-11-10', '2026-11-13');
    $other = bookStay(['401'], '2026-11-14', '2026-11-16');

    $refused = null;

    try {
        booking(fn () => ModifyReservation::make()->handle($original, stayChange('2026-11-12', '2026-11-15')));
    } catch (RoomNoLongerAvailable $exception) {
        $refused = $exception;
    }

    $kept = freshReservation($original->id);

    expect($refused?->taken)->toBe(['401' => ['2026-11-14']])
        ->and([$kept->check_in->toDateString(), $kept->check_out->toDateString(), $kept->grand_total])->toBe(['2026-11-10', '2026-11-13', '22770.00'])
        ->and($kept->items)->toHaveCount(1)
        ->and(lockedDates($original->id, '401'))->toBe(['2026-11-10', '2026-11-11', '2026-11-12'])
        ->and(lockedDates($other->id, '401'))->toBe(['2026-11-14', '2026-11-15'])
        ->and($kept->logs->pluck('action')->all())->not->toContain(ReservationLogAction::Modified);
});

it('swaps a room for another room and a whole villa, locking only the new rooms', function (): void {
    $reservation = bookStay(['401']);

    booking(fn () => ModifyReservation::make()->handle($reservation, stayChange('2026-11-10', '2026-11-13', ['402', 'C07'])));
    $changed = freshReservation($reservation->id);

    // (6,000 + 15,000) × 3 = 63,000; SC 6,300; VAT 15% of 69,300 = 10,395.
    expect($changed->items->pluck('item_type')->all())->toBe([ItemType::Room, ItemType::Cottage])
        ->and($changed->grand_total)->toBe('79695.00')
        ->and(lockedDates($reservation->id, '401'))->toBe([])
        ->and(booking(fn (): int => InventoryLock::query()->where('reservation_id', $reservation->id)->count()))->toBe((1 + 3) * 3);
});

it('keeps guests linked to a removed room on the booking', function (): void {
    $reservation = bookStay(['401', '402']);
    booking(fn () => $reservation->guests()->update(['reservation_item_id' => $reservation->items->first()?->id]));

    booking(fn () => ModifyReservation::make()->handle($reservation, stayChange('2026-11-10', '2026-11-13', ['402'])));

    expect(freshReservation($reservation->id)->guests->pluck('reservation_item_id')->all())->toBe([null]);
});

it('confirms a tentative booking whose payments now cover the smaller deposit', function (): void {
    Event::fake([ReservationConfirmed::class]);
    $reservation = bookStay(['401']);
    booking(fn () => ApplyPayment::make()->handle($reservation->id, '5000.00', '5000.00'));
    expect(freshReservation($reservation->id)->status)->toBe(ReservationStatus::Tentative);

    // One night: 7,590.00, deposit 2,277.00 — the 5,000 paid covers it.
    booking(fn () => ModifyReservation::make()->handle($reservation, stayChange('2026-11-10', '2026-11-11')));

    expect(freshReservation($reservation->id)->status)->toBe(ReservationStatus::Confirmed);
    Event::assertDispatched(ReservationConfirmed::class);
});

it('refuses to change a cancelled booking', function (): void {
    $reservation = bookStay(['401']);
    booking(fn () => CancelReservation::make()->handle($reservation, 'Guest changed plans'));

    booking(fn () => ModifyReservation::make()->handle(freshReservation($reservation->id), stayChange('2026-11-12', '2026-11-14')));
})->throws(ReservationNotChangeable::class);

describe('screens', function (): void {
    it('edits, reviews the new price and saves the change', function (): void {
        staffUser();
        $reservation = bookStay(['401']);
        $ids = bookingIds();
        $input = ['check_in' => '2026-11-12', 'check_out' => '2026-11-14', 'items' => [
            ['unit' => 'room:'.$ids['rooms']['401'], 'rate_plan' => $ids['plan'], 'adults' => 2, 'children' => 0, 'remove' => 0],
            ['unit' => '', 'rate_plan' => $ids['plan'], 'adults' => 2, 'children' => 0],
        ]];

        get(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}/edit"))->assertOk()->assertSeeHtml('data-item-row')->assertSee('Room 401');
        post(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}/edit"), $input)->assertOk()
            ->assertSeeInOrder([__('Grand total'), '22,770.00', '15,180.00'])->assertSeeHtml('data-save-changes');
        put(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}"), $input)
            ->assertRedirect(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}"))->assertSessionHas('success');

        expect(freshReservation($reservation->id)->grand_total)->toBe('15180.00');
    });

    it('returns to the edit page with the clash and keeps the booking', function (): void {
        staffUser();
        $reservation = bookStay(['401'], '2026-11-10', '2026-11-13');
        bookStay(['401'], '2026-11-14', '2026-11-16');
        $ids = bookingIds();

        put(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}"), ['check_in' => '2026-11-12', 'check_out' => '2026-11-15',
            'items' => [['unit' => 'room:'.$ids['rooms']['401'], 'rate_plan' => $ids['plan'], 'adults' => 2]]])
            ->assertRedirect(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}/edit"))->assertSessionHasErrors('items');

        expect(freshReservation($reservation->id)->check_out->toDateString())->toBe('2026-11-13');
    });

    it('validates the dates and the rooms', function (): void {
        staffUser();
        $reservation = bookStay(['401']);
        $plan = bookingIds()['plan'];

        post(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}/edit"), ['check_in' => '2026-11-12', 'check_out' => '2026-11-11',
            'items' => [['unit' => 'room:999999', 'rate_plan' => $plan, 'adults' => 2]]])->assertSessionHasErrors('check_out');
        post(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}/edit"), ['check_in' => '2026-11-12', 'check_out' => '2026-11-14',
            'items' => [['unit' => 'room:999999', 'rate_plan' => $plan, 'adults' => 2]]])->assertSessionHasErrors('items');
        post(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}/edit"), ['check_in' => '2026-11-12', 'check_out' => '2026-11-14',
            'items' => [['unit' => '', 'rate_plan' => $plan, 'adults' => 2]]])->assertSessionHasErrors('items');
    });

    it('is refused to staff without reservation.booking.update', function (): void {
        staffUser(DefaultRole::HousekeepingSupervisor);
        $reservation = bookStay(['401']);

        get(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}/edit"))->assertForbidden();
        put(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}"), ['check_in' => '2026-11-12', 'check_out' => '2026-11-14', 'items' => []])->assertForbidden();
    });

    it('does not open another tenant\'s booking', function (): void {
        bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'greenvalley'])));
        $theirs = bookStay(['401'], slug: 'greenvalley');
        staffUser();

        get(tenantUrl('sunrise', "/reservation/bookings/{$theirs->id}/edit"))->assertNotFound();
    });
});
