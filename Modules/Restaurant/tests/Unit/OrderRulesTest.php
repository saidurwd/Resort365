<?php

/*
| The pure order rules (Step 3.4): line pricing, modifier choices and which lines go on which KOT.
*/

use Modules\Restaurant\Services\KotGrouper;
use Modules\Restaurant\Services\ModifierRules;
use Modules\Restaurant\Services\OrderLinePricing;
use Tests\TestCase;

uses(TestCase::class);

it('prices a line as (unit price + modifiers) × quantity', function (): void {
    $pricing = new OrderLinePricing;

    expect($pricing->price('650.00', 1, []))->toBe(['modifier_total' => '0.00', 'line_total' => '650.00'])
        ->and($pricing->price('100.00', 2, ['30.00', '20.50']))->toBe(['modifier_total' => '50.50', 'line_total' => '301.00'])
        ->and($pricing->price('0.10', 3, ['0.20']))->toBe(['modifier_total' => '0.20', 'line_total' => '0.90'])
        ->and($pricing->price('450.00', 0, []))->toBe(['modifier_total' => '0.00', 'line_total' => '450.00'])
        ->and($pricing->subtotal(['650.00', '301.00', '0.90']))->toBe('951.90')
        ->and($pricing->subtotal([]))->toBe('0.00');
});

/**
 * @return list<array{id: int, name: string, min: int, max: int, options: array<int, array{name: string, price: string, active: bool}>}>
 */
function curryGroups(): array
{
    return [
        ['id' => 1, 'name' => 'Spice level', 'min' => 1, 'max' => 1, 'options' => [10 => ['name' => 'Mild', 'price' => '0.00', 'active' => true], 11 => ['name' => 'Hot', 'price' => '0.00', 'active' => true]]],
        ['id' => 2, 'name' => 'Add-ons', 'min' => 0, 'max' => 2, 'options' => [20 => ['name' => 'Extra cheese', 'price' => '80.00', 'active' => true],
            21 => ['name' => 'Fried egg', 'price' => '40.00', 'active' => true], 22 => ['name' => 'Bacon', 'price' => '120.00', 'active' => true], 23 => ['name' => 'Old', 'price' => '5.00', 'active' => false]]],
    ];
}

it('accepts choices that fit the item\'s groups, in group order', function (): void {
    $check = (new ModifierRules)->check(curryGroups(), [21, 11, 21]);

    expect($check['errors'])->toBe([])
        ->and($check['selected'])->toBe([
            ['id' => 11, 'group' => 'Spice level', 'name' => 'Hot', 'price' => '0.00'],
            ['id' => 21, 'group' => 'Add-ons', 'name' => 'Fried egg', 'price' => '40.00'],
        ]);
});

it('refuses missing required choices, too many choices and foreign or inactive options', function (): void {
    $rules = new ModifierRules;

    expect($rules->check(curryGroups(), [])['errors'])->toBe(['Choose a spice level.'])
        ->and($rules->check(curryGroups(), [10, 11])['errors'])->toBe(['Choose at most 1 for Spice level.'])
        ->and($rules->check(curryGroups(), [10, 20, 21, 22])['errors'])->toBe(['Choose at most 2 for Add-ons.'])
        ->and($rules->check(curryGroups(), [10, 99])['errors'])->toBe(['Some choices do not belong to this item.'])
        ->and($rules->check(curryGroups(), [10, 23])['errors'])->toBe(['Some choices do not belong to this item.'])
        ->and($rules->check(curryGroups(), [10, 99])['selected'])->toBe([])
        ->and($rules->check([], [])['errors'])->toBe([]);
});

it('puts the pending, unheld lines on one ticket per station, in the order they were added', function (): void {
    $lines = [
        ['id' => 1, 'station_id' => 5, 'status' => 'pending', 'held' => false, 'course' => 'main'],
        ['id' => 2, 'station_id' => 7, 'status' => 'pending', 'held' => false, 'course' => 'drink'],
        ['id' => 3, 'station_id' => 5, 'status' => 'sent', 'held' => false, 'course' => 'main'],
        ['id' => 4, 'station_id' => 5, 'status' => 'pending', 'held' => true, 'course' => 'dessert'],
        ['id' => 5, 'station_id' => 5, 'status' => 'pending', 'held' => false, 'course' => 'side'],
        ['id' => 6, 'station_id' => null, 'status' => 'pending', 'held' => false, 'course' => 'main'],
        ['id' => 7, 'station_id' => 9, 'status' => 'pending', 'held' => true, 'course' => 'main'],
    ];
    $grouper = new KotGrouper;

    expect($grouper->group($lines))->toBe([5 => [1, 5], 7 => [2], 'none' => [6]])
        ->and($grouper->group($lines, 'dessert'))->toBe([5 => [4]])
        ->and($grouper->group($lines, 'starter'))->toBe([])
        ->and($grouper->group([]))->toBe([]);
});
