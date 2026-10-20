<?php

/*
| Shared setup for the Reports tests (plain functions): tenant "sunrise" from the booking tests (property CXB, 8 rooms, plan Room Only,
| guest Rahim Uddin), plus helpers for night audit statistics, bookings and a second property.
*/

use Illuminate\Support\Facades\DB;
use Modules\FrontOffice\Models\DailyStatistic;
use Modules\Reservation\Enums\LockType;
use Modules\Reservation\Models\InventoryLock;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationItem;

require_once __DIR__.'/../../../Reservation/tests/Support/booking-setup.php';
require_once __DIR__.'/../../../Reservation/tests/Support/management-setup.php';
require_once __DIR__.'/reports-fixtures.php';

/**
 * One night audit day of the property.
 *
 * @param  array<string, mixed>  $overrides
 */
function rpStat(string $date, array $overrides = [], ?int $propertyId = null): DailyStatistic
{
    return booking(fn (): DailyStatistic => DailyStatistic::factory()->create(['property_id' => $propertyId ?? bookingIds()['property'], 'business_date' => $date, ...$overrides]));
}

/**
 * A booking with `$rooms` rooms (items) of the property.
 *
 * @param  array<string, mixed>  $overrides
 */
function rpBook(array $overrides = [], int $rooms = 1): Reservation
{
    return booking(function () use ($overrides, $rooms): Reservation {
        $ids = bookingIds();
        $reservation = Reservation::factory()->create([
            'property_id' => $ids['property'], 'primary_guest_id' => $ids['guest'], 'rate_plan_id' => $ids['plan'], 'status' => 'checked_out', 'check_in' => '2026-03-10', 'check_out' => '2026-03-12', 'grand_total' => '10000.00', ...$overrides,
        ]);
        $numbers = array_keys($ids['rooms']);

        for ($i = 0; $i < $rooms; $i++) {
            ReservationItem::factory()->create(['property_id' => $ids['property'], 'reservation_id' => $reservation->id, 'room_id' => $ids['rooms'][$numbers[$i]], 'check_in' => $reservation->check_in->toDateString(), 'check_out' => $reservation->check_out->toDateString()]);
        }

        return $reservation;
    });
}

function rpLock(string $room, string $date, LockType $type): InventoryLock
{
    return booking(fn (): InventoryLock => InventoryLock::factory()->create(['property_id' => bookingIds()['property'], 'room_id' => bookingIds()['rooms'][$room], 'stay_date' => $date, 'lock_type' => $type]));
}

/** A second property of the tenant (a different resort), in the given currency. */
function rpProperty(string $code, string $currency = 'BDT'): int
{
    return booking(function () use ($code, $currency): int {
        DB::table('currencies')->insertOrIgnore(['code' => $currency, 'name' => $currency, 'symbol' => $currency, 'decimals' => 2]);

        return DB::table('properties')->insertGetId([
            'tenant_id' => tenant('sunrise')->id, 'code' => $code, 'name' => $code.' Resort', 'country_code' => 'BD', 'timezone' => 'Asia/Dhaka', 'currency_code' => $currency, 'business_date' => now()->toDateString(),
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    });
}

/** A country of the reference data, for the nationality tests. */
function rpCountry(string $code = 'GB', string $name = 'United Kingdom', string $iso3 = 'GBR', string $numeric = '826'): void
{
    DB::table('countries')->insertOrIgnore(['code' => $code, 'iso3' => $iso3, 'numeric_code' => $numeric, 'name' => $name]);
}
