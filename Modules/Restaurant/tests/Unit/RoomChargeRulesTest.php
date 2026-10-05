<?php

/*
| Charge to room and meal periods (Step 3.7): the bill's taxes in a payment charged elsewhere, and the
| meal running at a time.
*/

use Modules\Restaurant\Enums\MealPeriod;
use Modules\Restaurant\Services\TaxShare;

/**
 * @return list<array{code: string, name: string, rate: string, amount: string}>
 */
function billTaxes(): array
{
    return [
        ['code' => 'SC', 'name' => 'Service charge', 'rate' => '10', 'amount' => '44.00'],
        ['code' => 'VAT', 'name' => 'VAT', 'rate' => '15', 'amount' => '72.60'],
    ];
}

it('posts the whole bill\'s taxes when the whole bill is charged', function (): void {
    expect((new TaxShare)->of(billTaxes(), '556.60', '556.60'))->toBe(['net' => '440.00', 'taxes' => ['Service charge' => '44.00', 'VAT' => '72.60']]);
});

it('shares the taxes when part of the bill is charged', function (): void {
    // 300.00 of 556.60: SC 44 × 300 / 556.60 = 23.72, VAT 39.13, net 237.15.
    $share = (new TaxShare)->of(billTaxes(), '556.60', '300.00');

    expect($share)->toBe(['net' => '237.15', 'taxes' => ['Service charge' => '23.72', 'VAT' => '39.13']])
        ->and(bcadd(bcadd($share['net'], $share['taxes']['Service charge'], 2), $share['taxes']['VAT'], 2))->toBe('300.00');
});

it('handles a bill without taxes', function (): void {
    expect((new TaxShare)->of([], '100.00', '40.00'))->toBe(['net' => '40.00', 'taxes' => []]);
});

it('suggests the meal running at a time', function (): void {
    expect(MealPeriod::at('07:30', '11:00', '16:00'))->toBe(MealPeriod::Breakfast)
        ->and(MealPeriod::at('11:00', '11:00', '16:00'))->toBe(MealPeriod::Lunch)
        ->and(MealPeriod::at('15:59', '11:00', '16:00'))->toBe(MealPeriod::Lunch)
        ->and(MealPeriod::at('20:15', '11:00', '16:00'))->toBe(MealPeriod::Dinner);
});
