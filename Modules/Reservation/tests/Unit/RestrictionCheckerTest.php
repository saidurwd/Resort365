<?php

/*
| Step 1.5 "Done when": restrictions respected.
*/

use Carbon\CarbonImmutable;
use Modules\Rates\DTOs\RestrictionSet;
use Modules\Reservation\DTOs\RestrictionViolation;
use Modules\Reservation\Enums\StayRestriction;
use Modules\Reservation\Services\RestrictionChecker;

/**
 * @param  array<string, RestrictionSet>  $byDate
 * @return list<string>
 */
function blocked(array $byDate, string $checkIn, string $checkOut): array
{
    return array_map(fn (RestrictionViolation $v): string => $v->restriction->value.($v->nights ? ':'.$v->nights : ''),
        new RestrictionChecker()->check($byDate, CarbonImmutable::parse($checkIn), CarbonImmutable::parse($checkOut)));
}

it('allows a stay without restrictions', function (): void {
    expect(blocked([], '2026-12-30', '2027-01-02'))->toBe([]);
});

it('blocks a stop-sell on any night of the stay, but not on the departure date', function (): void {
    $stop = ['2026-12-31' => new RestrictionSet(stopSell: true)];

    expect(blocked($stop, '2026-12-30', '2027-01-02'))->toBe(['stop_sell'])
        ->and(blocked($stop, '2026-12-29', '2026-12-31'))->toBe([]);
});

it('applies minimum and maximum stay as set on the arrival night', function (): void {
    $newYear = ['2026-12-30' => new RestrictionSet(minStay: 2), '2026-12-31' => new RestrictionSet(minStay: 3, maxStay: 5)];

    expect(blocked($newYear, '2026-12-30', '2026-12-31'))->toBe(['min_stay:2'])
        ->and(blocked($newYear, '2026-12-30', '2027-01-01'))->toBe([])
        ->and(blocked($newYear, '2026-12-31', '2027-01-02'))->toBe(['min_stay:3'])
        ->and(blocked($newYear, '2026-12-31', '2027-01-07'))->toBe(['max_stay:5']);
});

it('checks closed to arrival on check-in and closed to departure on check-out', function (): void {
    $closed = ['2026-12-24' => new RestrictionSet(closedToArrival: true), '2026-12-26' => new RestrictionSet(closedToDeparture: true)];

    expect(blocked($closed, '2026-12-24', '2026-12-25'))->toBe(['closed_to_arrival'])
        ->and(blocked($closed, '2026-12-23', '2026-12-25'))->toBe([])
        ->and(blocked($closed, '2026-12-25', '2026-12-26'))->toBe(['closed_to_departure'])
        ->and(blocked($closed, '2026-12-25', '2026-12-27'))->toBe([]);
});

it('reports every violation at once', function (): void {
    $all = ['2026-12-24' => new RestrictionSet(minStay: 4, closedToArrival: true, stopSell: true)];

    expect(blocked($all, '2026-12-24', '2026-12-25'))->toBe([StayRestriction::StopSell->value, 'closed_to_arrival', 'min_stay:4']);
});
