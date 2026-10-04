<?php

/*
| MenuAvailability: when an outlet sells an item and for how much (menu schedules).
| 2026-11-09 is a Monday.
*/

use Carbon\CarbonImmutable;
use Modules\Restaurant\Services\MenuAvailability;

/**
 * @return array<int, array{id: int, days: list<string>, start: string, end: string, adjustment: string, active: bool}>
 */
function menuSchedules(): array
{
    return [
        1 => ['id' => 1, 'days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], 'start' => '07:00', 'end' => '10:30', 'adjustment' => '0', 'active' => true],
        2 => ['id' => 2, 'days' => ['fri', 'sat'], 'start' => '17:00', 'end' => '19:00', 'adjustment' => '-20', 'active' => true],
        3 => ['id' => 3, 'days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], 'start' => '11:00', 'end' => '01:00', 'adjustment' => '0', 'active' => true],
        4 => ['id' => 4, 'days' => ['mon'], 'start' => '12:00', 'end' => '15:00', 'adjustment' => '10', 'active' => false],
    ];
}

function at(string $when): CarbonImmutable
{
    return CarbonImmutable::parse($when);
}

it('sells items without schedules whenever the outlet sells them, at the outlet price', function (): void {
    $menu = new MenuAvailability;

    expect($menu->isOnSale([], menuSchedules(), at('2026-11-09 03:00')))->toBeTrue()
        ->and($menu->price('350', [], menuSchedules(), at('2026-11-09 03:00')))->toBe('350.00');
});

it('sells breakfast only between 07:00 and 10:30', function (string $time, bool $onSale): void {
    expect((new MenuAvailability)->isOnSale([1], menuSchedules(), at('2026-11-09 '.$time)))->toBe($onSale);
})->with([['06:59', false], ['07:00', true], ['10:29', true], ['10:30', false]]);

it('runs a window past midnight from the day it started', function (): void {
    $menu = new MenuAvailability;

    expect($menu->isOnSale([3], menuSchedules(), at('2026-11-09 23:30')))->toBeTrue()
        ->and($menu->isOnSale([3], menuSchedules(), at('2026-11-10 00:45')))->toBeTrue()
        ->and($menu->isOnSale([3], menuSchedules(), at('2026-11-10 01:00')))->toBeFalse()
        ->and($menu->isOnSale([3], menuSchedules(), at('2026-11-10 10:00')))->toBeFalse();
});

it('takes the lowest price of the schedules running: happy hour on Fridays', function (): void {
    $menu = new MenuAvailability;

    expect($menu->price('350', [2, 3], menuSchedules(), at('2026-11-13 18:00')))->toBe('280.00')
        ->and($menu->price('350', [2, 3], menuSchedules(), at('2026-11-13 20:00')))->toBe('350.00')
        ->and($menu->price('350', [2, 3], menuSchedules(), at('2026-11-09 18:00')))->toBe('350.00')
        ->and($menu->price('333.33', [2], menuSchedules(), at('2026-11-13 18:00')))->toBe('266.66')
        ->and($menu->price('350', [2], menuSchedules(), at('2026-11-13 12:00')))->toBeNull();
});

it('ignores inactive schedules', function (): void {
    expect((new MenuAvailability)->isOnSale([4], menuSchedules(), at('2026-11-09 13:00')))->toBeFalse();
});
