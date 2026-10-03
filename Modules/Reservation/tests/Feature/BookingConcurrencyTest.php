<?php

/*
| Step 1.6 "Done when": two parallel bookings for the same room-night leave exactly one successful.
| Separate PHP processes book at the same moment against committed data (no RefreshDatabase
| transaction); the unique (room_id, stay_date) lock index decides. Ten rounds each.
*/

use App\Actions\Tenancy\CreateTenant;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;
use Modules\Reservation\Models\InventoryLock;
use Modules\Reservation\Models\Reservation;

require_once __DIR__.'/../Support/booking-setup.php';

uses(DatabaseTruncation::class);

/**
 * Runs the given bookings at the same time and returns each one's output.
 *
 * @param  list<string>  $units  one booking per entry, e.g. "room:12" or "cottage:3,room:9"
 * @return list<string>
 */
function bookInParallel(array $units, string $checkIn, string $checkOut): array
{
    $ids = bookingIds('race');
    $tenant = tenant('race');
    $connection = config('database.connections.mysql');
    $env = [
        'APP_ENV' => 'testing', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'SESSION_DRIVER' => 'array', 'DB_CONNECTION' => 'mysql',
        'DB_HOST' => (string) $connection['host'], 'DB_PORT' => (string) $connection['port'], 'DB_DATABASE' => (string) $connection['database'],
        'DB_USERNAME' => (string) $connection['username'], 'DB_PASSWORD' => (string) $connection['password'],
    ];

    $results = Process::pool(function (Pool $pool) use ($units, $ids, $tenant, $env, $checkIn, $checkOut): void {
        foreach ($units as $i => $unit) {
            $pool->as((string) $i)->env($env)->timeout(120)->command([PHP_BINARY, __DIR__.'/../Support/book.php',
                (string) $tenant->id, (string) $ids['property'], (string) $ids['guest'], (string) $ids['plan'], $checkIn, $checkOut, $unit]);
        }
    })->start()->wait();

    return array_values(array_map(fn ($result): string => trim($result->output()).($result->errorOutput() !== '' ? ' ERR '.mb_substr($result->errorOutput(), 0, 300) : ''), $results->collect()->all()));
}

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(CreateTenant::make()->handle('race', 'Race Resort')));
});

it('lets exactly one of two parallel bookings take the same room-night', function (): void {
    $room = 'room:'.bookingIds('race')['rooms']['401'];

    foreach (range(1, 10) as $round) {
        $night = now()->addDays(30 + $round)->toDateString();
        $outputs = bookInParallel([$room, $room], $night, now()->addDays(31 + $round)->toDateString());

        expect(collect($outputs)->filter(fn (string $out): bool => str_starts_with($out, 'BOOKED'))->count())->toBe(1, "round {$round}: ".implode(' | ', $outputs))
            ->and(collect($outputs)->filter(fn (string $out): bool => $out === 'TAKEN')->count())->toBe(1, "round {$round}: ".implode(' | ', $outputs));
    }

    booking(function (): void {
        expect(Reservation::query()->count())->toBe(10)
            ->and(InventoryLock::query()->count())->toBe(10);
    }, 'race');
});

it('lets only one of a whole-cottage booking and a booking of one of its rooms win', function (): void {
    $ids = bookingIds('race');

    foreach (range(1, 10) as $round) {
        $night = now()->addDays(60 + $round)->toDateString();
        $outputs = bookInParallel(['cottage:'.$ids['cottages']['C07'], 'room:'.$ids['rooms']['702']], $night, now()->addDays(61 + $round)->toDateString());

        expect(collect($outputs)->filter(fn (string $out): bool => str_starts_with($out, 'BOOKED'))->count())->toBe(1, "round {$round}: ".implode(' | ', $outputs));
    }

    // Each round left either the 3 villa rooms or the 1 room locked, never both.
    booking(function (): void {
        expect(Reservation::query()->count())->toBe(10)
            ->and(InventoryLock::query()->selectRaw('stay_date, count(*) as locks')->groupBy('stay_date')->pluck('locks')->every(fn ($locks): bool => in_array((int) $locks, [1, 3], true)))->toBeTrue();
    }, 'race');
});
