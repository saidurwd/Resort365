<?php

/*
| The pure restaurant report calculators and the table reservation clash rule (Step 3.8).
*/

use Carbon\CarbonImmutable;
use Modules\Restaurant\Services\Reports\MealPlanReport;
use Modules\Restaurant\Services\Reports\SalesReport;
use Modules\Restaurant\Services\TableAvailability;

it('groups sales by label with shares of the net, biggest first', function (): void {
    $rows = [
        ['label' => 'Curry', 'qty' => 2.0, 'net' => 130000, 'discount' => 0, 'tax' => 32500, 'gross' => 162500],
        ['label' => 'Naan', 'qty' => 3.0, 'net' => 30000, 'discount' => 3000, 'tax' => 7500, 'gross' => 37500],
        ['label' => 'Curry', 'qty' => 1.0, 'net' => 65000, 'discount' => 0, 'tax' => 16250, 'gross' => 81250],
    ];
    $groups = (new SalesReport)->group($rows);

    expect(array_column($groups, 'label'))->toBe(['Curry', 'Naan'])
        ->and($groups[0])->toMatchArray(['qty' => 3.0, 'net' => 195000, 'gross' => 243750, 'share' => '86.7'])
        ->and($groups[1])->toMatchArray(['net' => 30000, 'discount' => 3000, 'share' => '13.3'])
        ->and(array_column((new SalesReport)->group($rows, true), 'label'))->toBe(['Curry', 'Naan'])
        ->and((new SalesReport)->group([]))->toBe([]);
});

it('orders hour labels naturally and works out spend per cover', function (): void {
    $rows = array_map(fn (string $hour): array => ['label' => $hour, 'qty' => 1.0, 'net' => 100, 'discount' => 0, 'tax' => 0, 'gross' => 100], ['19:00', '08:00', '13:00']);

    expect(array_column((new SalesReport)->group($rows, true), 'label'))->toBe(['08:00', '13:00', '19:00'])
        ->and((new SalesReport)->perCover(100001, 4))->toBe(25000)
        ->and((new SalesReport)->perCover(10, 0))->toBe(0);
});

it('compares meals included with meals taken', function (): void {
    $rows = (new MealPlanReport)->compare(
        ['2026-10-05' => ['breakfast' => 4, 'dinner' => 2], '2026-10-06' => ['breakfast' => 2]],
        ['2026-10-05' => ['breakfast' => 3], '2026-10-06' => ['breakfast' => 3, 'lunch' => 1]],
    );

    expect($rows)->toBe([
        ['date' => '2026-10-05', 'period' => 'breakfast', 'included' => 4, 'taken' => 3, 'not_taken' => 1, 'over' => 0],
        ['date' => '2026-10-05', 'period' => 'dinner', 'included' => 2, 'taken' => 0, 'not_taken' => 2, 'over' => 0],
        ['date' => '2026-10-06', 'period' => 'breakfast', 'included' => 2, 'taken' => 3, 'not_taken' => 0, 'over' => 1],
        ['date' => '2026-10-06', 'period' => 'lunch', 'included' => 0, 'taken' => 1, 'not_taken' => 0, 'over' => 1],
    ]);
});

it('finds table clashes between overlapping slots only', function (): void {
    $availability = new TableAvailability;
    $at = fn (string $time): CarbonImmutable => CarbonImmutable::parse('2026-10-07 '.$time, 'UTC');
    $others = [['start' => $at('19:00'), 'minutes' => 90]];

    expect($availability->clashes($at('18:00'), 90, $others))->toBeTrue()
        ->and($availability->clashes($at('18:00'), 60, $others))->toBeFalse()
        ->and($availability->clashes($at('20:29'), 90, $others))->toBeTrue()
        ->and($availability->clashes($at('20:30'), 90, $others))->toBeFalse()
        ->and($availability->clashes($at('19:30'), 30, $others))->toBeTrue()
        ->and($availability->clashes($at('19:00'), 90, []))->toBeFalse()
        ->and($availability->seats(4, 4))->toBeTrue()
        ->and($availability->seats(4, 5))->toBeFalse();
});
