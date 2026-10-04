<?php

/*
| Tape chart (Step 2.5). "Done when": dragging a booking onto an occupied room is refused with a
| clear message.
|
| Rooms 401/402 (cottage C04), 701–703 (C07), 801–803 (C08), all Deluxe King. The chart opens on
| the day before the business date, so tonight is column 1. Cottages are listed in catalog order:
| Lagoon (C07), Palm (C04), Sunset (C08).
*/
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Modules\Property\Models\Property;
use Modules\Property\Models\Room;
use Modules\Property\Models\RoomType;
use Modules\Reservation\Contracts\StayOperations;
use Modules\Reservation\Enums\LockType;
use Modules\Reservation\Models\InventoryLock;
use Modules\Reservation\Models\Reservation;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

require_once __DIR__.'/../Support/management-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    Notification::fake();
});

function chartTonight(): CarbonImmutable
{
    return CarbonImmutable::parse(booking(fn (): string => Property::query()->where('code', 'CXB')->sole()->business_date->toDateString()));
}

/**
 * A confirmed booking from tonight + $from for $nights nights.
 *
 * @param  list<string>  $units
 */
function chartBooking(array $units, int $from, int $nights): Reservation
{
    return bookStay($units, chartTonight()->addDays($from)->toDateString(), chartTonight()->addDays($from + $nights)->toDateString(),
        ['depositPercent' => '0', 'allowDepositOverride' => true]);
}

/**
 * @return list<string>
 */
function chartLockDates(int $reservationId, string $room): array
{
    return booking(fn (): array => InventoryLock::query()->where('reservation_id', $reservationId)->where('room_id', bookingIds()['rooms'][$room])
        ->orderBy('stay_date')->get()->map(fn (InventoryLock $lock): string => $lock->stay_date->toDateString())->all());
}

/**
 * @return TestResponse<Response>
 */
function moveOnChart(Reservation $reservation, string $room): TestResponse
{
    return postJson(tenantUrl('sunrise', '/reservation/tape-chart/move'), ['item_id' => $reservation->items->sole()->id, 'room_id' => bookingIds()['rooms'][$room]]);
}

it('shows bookings and blocks in their room rows and night columns', function (): void {
    staffUser();
    $stay = chartBooking(['402'], 2, 3);
    $villa = chartBooking(['C08'], 0, 2);
    booking(fn () => InventoryLock::query()->create(['property_id' => bookingIds()['property'], 'room_id' => bookingIds()['rooms']['701'],
        'stay_date' => chartTonight()->addDays(5)->toDateString(), 'lock_type' => LockType::OwnerBlock, 'note' => 'Owner family']));

    get(tenantUrl('sunrise', '/reservation/tape-chart'))->assertOk()
        ->assertSeeHtml('data-from="'.chartTonight()->subDay()->toDateString().'"')
        ->assertSeeHtmlInOrder(['data-room="402"', 'data-code="'.$stay->code.'" data-start="3" data-span="3"', 'data-room="801"'])
        ->assertSeeHtmlInOrder(['data-room="701"', 'data-start="6" data-span="1"', 'data-room="702"'])
        ->assertSee('Owner family')
        ->assertSeeHtmlInOrder(['data-room="803"', 'data-code="'.$villa->code.'" data-start="1" data-span="2"'])
        ->assertSeeHtml('draggable="true" @dragstart="start($event, '.$stay->items->sole()->id.')"')
        ->assertDontSeeHtml('@dragstart="start($event, '.$villa->items->sole()->id.')"');
});

it('moves through a 30-day window and back', function (): void {
    staffUser();
    $from = chartTonight()->addDays(20)->toDateString();

    get(tenantUrl('sunrise', "/reservation/tape-chart?from={$from}&days=30"))->assertOk()->assertSeeHtml('tape-chart--30')->assertSeeHtml('data-from="'.$from.'"');
    getJson(tenantUrl('sunrise', '/reservation/tape-chart?days=7'))->assertUnprocessable()->assertJsonValidationErrors('days');
});

it('refuses a drag onto an occupied room with a clear message', function (): void {
    staffUser();
    $moving = chartBooking(['401'], 2, 3);
    chartBooking(['402'], 3, 2);

    moveOnChart($moving, '402')->assertUnprocessable()->assertJson([
        'ok' => false,
        'message' => 'Room 402 is taken on '.chartTonight()->addDays(3)->format('d M').', '.chartTonight()->addDays(4)->format('d M').'.',
    ]);

    expect(chartLockDates($moving->id, '401'))->toHaveCount(3)->and(chartLockDates($moving->id, '402'))->toBe([]);
});

it('moves an upcoming booking to a free room of the same type at the same price', function (): void {
    staffUser();
    $moving = chartBooking(['401'], 2, 3);

    moveOnChart($moving, '402')->assertOk()->assertJson(['ok' => true, 'message' => 'Moved to room 402, same price.'])->assertSessionHas('success');

    expect(chartLockDates($moving->id, '402'))->toHaveCount(3)
        ->and(chartLockDates($moving->id, '401'))->toBe([])
        ->and(freshReservation($moving->id)->grand_total)->toBe($moving->grand_total)
        ->and(freshReservation($moving->id)->items->sole()->room_id)->toBe(bookingIds()['rooms']['402']);
});

it('moves an in-house guest from tonight at the same rate', function (): void {
    staffUser();
    $stay = chartBooking(['401'], -1, 3);
    booking(fn () => app(StayOperations::class)->checkIn($stay->id));

    moveOnChart(freshReservation($stay->id), '701')->assertOk()->assertJson(['ok' => true]);

    expect(chartLockDates($stay->id, '401'))->toBe([chartTonight()->subDay()->toDateString()])
        ->and(chartLockDates($stay->id, '701'))->toBe([chartTonight()->toDateString(), chartTonight()->addDay()->toDateString()])
        ->and(freshReservation($stay->id)->grand_total)->toBe($stay->grand_total);
});

it('refuses a room of another type before arrival, whole cottages and finished stays', function (): void {
    staffUser();
    $moving = chartBooking(['401'], 2, 2);
    booking(function (): void {
        $suite = RoomType::factory()->create(['property_id' => bookingIds()['property'], 'code' => 'ST', 'name' => 'Suite']);
        Room::factory()->create(['property_id' => bookingIds()['property'], 'cottage_id' => bookingIds()['cottages']['C04'], 'room_type_id' => $suite->id, 'number' => '403']);
    });

    moveOnChart($moving, '403')->assertUnprocessable()->assertJsonPath('message', 'Room 403 is another room type; change the room type with Modify booking, which re-prices it.');

    $villa = chartBooking(['C08'], 2, 2);
    moveOnChart($villa, '701')->assertUnprocessable()->assertJsonPath('message', 'A whole cottage is moved with Change stay, not on the chart.');

    booking(fn () => Reservation::query()->whereKey($moving->id)->sole()->items()->update(['status' => 'cancelled']));
    moveOnChart($moving, '402')->assertUnprocessable()->assertJsonPath('message', 'This booking can no longer be moved.');
});

it('needs reservation.booking.view to see the chart and reservation.booking.update to move', function (): void {
    staffUser(DefaultRole::Chef);
    get(tenantUrl('sunrise', '/reservation/tape-chart'))->assertForbidden();

    $moving = chartBooking(['401'], 2, 2);
    staffUser(DefaultRole::Auditor);
    get(tenantUrl('sunrise', '/reservation/tape-chart'))->assertOk()->assertDontSeeHtml('draggable="true"');
    moveOnChart($moving, '402')->assertForbidden();

    expect(chartLockDates($moving->id, '401'))->toHaveCount(2);
});

it('does not move another tenant\'s booking', function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'greenvalley'])));
    $theirs = bookStay(['401'], chartTonight()->addDays(2)->toDateString(), chartTonight()->addDays(4)->toDateString(), ['depositPercent' => '0', 'allowDepositOverride' => true], 'greenvalley');
    staffUser();

    postJson(tenantUrl('sunrise', '/reservation/tape-chart/move'), ['item_id' => $theirs->items->sole()->id, 'room_id' => bookingIds()['rooms']['402']])
        ->assertUnprocessable()->assertJsonValidationErrors('item_id');
});

it('starts a booking for an empty cell\'s dates', function (): void {
    staffUser();
    $checkIn = chartTonight()->addDays(3)->toDateString();
    $checkOut = chartTonight()->addDays(4)->toDateString();

    get(tenantUrl('sunrise', '/reservation/tape-chart'))->assertOk()
        ->assertSeeHtml(e(route('reservation.bookings.create', ['tenant' => 'sunrise', 'check_in' => $checkIn, 'check_out' => $checkOut])));

    get(tenantUrl('sunrise', "/reservation/bookings/new?check_in={$checkIn}&check_out={$checkOut}"))->assertOk()
        ->assertSeeHtml('value="'.$checkIn.'"')->assertSeeHtml('value="'.$checkOut.'"');

    // Dates in the past or the wrong way round are ignored.
    get(tenantUrl('sunrise', '/reservation/bookings/new?check_in=2020-01-01&check_out=2020-01-03'))->assertOk()->assertDontSeeHtml('value="2020-01-01"');
});
