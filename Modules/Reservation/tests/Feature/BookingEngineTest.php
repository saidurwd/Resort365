<?php

/*
| Reservation's BookingEngine for other modules (Step 8.2b): availability per sales channel, items checked against it (whole
| cottages before rooms), quotes, bookings and cancelling. Setup (booking-setup.php): Sunrise CXB, Deluxe King rooms 401/402,
| 701–703 (villa C07) and 801–803 (villa C08) at 6,000; C07 and C08 sold whole at 15,000; Room Only plan; deposit 30%.
*/

use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Guest\Models\Guest;
use Modules\Property\Models\RoomType;
use Modules\Rates\Models\RatePlan;
use Modules\Reservation\Contracts\BookingEngine;
use Modules\Reservation\DTOs\AvailabilitySearch;
use Modules\Reservation\DTOs\NewReservation;
use Modules\Reservation\DTOs\Occupancy;
use Modules\Reservation\Enums\ItemType;
use Modules\Reservation\Enums\ReservationSource;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Exceptions\ReservationNotChangeable;

require_once __DIR__.'/../Support/management-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
});

/** @return array{CarbonImmutable, CarbonImmutable} */
function beDates(): array
{
    return [CarbonImmutable::today()->addDays(20), CarbonImmutable::today()->addDays(22)];
}

it('searches through a sales channel', function (): void {
    [$in, $out] = beDates();
    $ids = bookingIds();
    $desk = booking(fn (): int => RatePlan::factory()->create(['property_id' => $ids['property'], 'code' => 'DESK', 'channels' => ['front_desk']])->id);
    $engine = app(BookingEngine::class);

    $online = booking(fn () => $engine->availability(new AvailabilitySearch($ids['property'], $desk, $in, $out, new Occupancy(2), null, 'online')));
    $front = booking(fn () => $engine->availability(new AvailabilitySearch($ids['property'], $desk, $in, $out, new Occupancy(2))));

    expect($online?->planViolations)->not->toBe([])->and($front?->planViolations)->toBe([]);
});

it('takes the whole cottages first, so a room of a type never comes from a cottage booked whole', function (): void {
    [$in, $out] = beDates();
    $ids = bookingIds();
    $type = booking(fn (): int => (int) RoomType::query()->value('id'));
    // Leave only C07's rooms and 801 free by holding 401, 402, 802 and 803.
    bookStay(['401', '402', '802', '803'], $in->toDateString(), $out->toDateString());

    $items = booking(fn (): array => app(BookingEngine::class)->resolveItems($ids['property'], $ids['plan'], $in, $out, [
        ['type' => 'room', 'room_type_id' => $type, 'adults' => 2],
        ['type' => 'cottage', 'cottage_id' => $ids['cottages']['C07'], 'adults' => 4],
    ], channel: 'online'));

    expect($items)->toHaveCount(2)
        ->and([$items[0]->type, $items[0]->unitId])->toBe([ItemType::Room, $ids['rooms']['801']])
        ->and([$items[1]->type, $items[1]->unitId])->toBe([ItemType::Cottage, $ids['cottages']['C07']]);

    expect(fn () => booking(fn (): array => app(BookingEngine::class)->resolveItems($ids['property'], $ids['plan'], $in, $out, [
        ['type' => 'cottage', 'cottage_id' => $ids['cottages']['C07'], 'adults' => 4],
        ['type' => 'room', 'room_id' => $ids['rooms']['701'], 'adults' => 2],
    ])))->toThrow(ValidationException::class);
});

it('quotes, books and cancels', function (): void {
    [$in, $out] = beDates();
    $ids = bookingIds();
    $engine = app(BookingEngine::class);
    $data = new NewReservation($ids['property'], $in, $out, booking(fn (): array => $engine->resolveItems($ids['property'], $ids['plan'], $in, $out, [['type' => 'room', 'room_id' => $ids['rooms']['401'], 'adults' => 2]])), $ids['guest'], ReservationSource::Website);

    expect(booking(fn () => $engine->quote($data))->total)->toBe('15180.00');

    $booked = booking(fn () => $engine->book($data));
    expect($booked->status)->toBe(ReservationStatus::Tentative)->and($booked->grandTotal)->toBe('15180.00')
        ->and(booking(fn () => $engine->cancellationQuote($booked->id))?->fee)->toBe('0.00');

    $cancelled = booking(fn () => $engine->cancel($booked->id, 'Changed plans'));
    expect($cancelled->status)->toBe(ReservationStatus::Cancelled)
        ->and(booking(fn () => $engine->cancellationQuote($booked->id)))->toBeNull()
        ->and(fn () => booking(fn () => $engine->cancel($booked->id, 'Again')))->toThrow(ReservationNotChangeable::class);
});

it('finds a guest only when both email and phone match one profile', function (): void {
    booking(function (): void {
        Guest::factory()->create(['email' => 'nadia@example.com', 'phone' => '+8801911000003']);
        Guest::factory()->create(['email' => 'shared@example.com', 'phone' => '+8801911000004']);
        Guest::factory()->create(['email' => 'shared@example.com', 'phone' => '+8801911000004']);
    });
    $lookup = app(GuestLookup::class);

    expect(booking(fn () => $lookup->matchContact('Nadia@Example.com', '01911-000003'))?->email)->toBe('nadia@example.com')
        ->and(booking(fn () => $lookup->matchContact('nadia@example.com', '01911-999999')))->toBeNull()
        ->and(booking(fn () => $lookup->matchContact('shared@example.com', '01911-000004')))->toBeNull();
});
