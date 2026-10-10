<?php

use Modules\Accounting\Services\NightRevenueBuilder;
use Modules\Billing\DTOs\ChargeFact;

/**
 * @param  array<string, string>  $taxLines
 */
function charge(string $code, string $category, string $amount, array $taxLines = [], string $tax = '0.00', string $meal = '0.00'): ChargeFact
{
    return new ChargeFact(1, 1, 1, 1, '2026-10-10', $code, $category, $amount, $tax, $taxLines, $meal, false);
}

it('adds revenue by charge code and taxes by name, and totals the guest ledger debit', function (): void {
    $built = (new NightRevenueBuilder)->build([
        charge('ROOM', 'room', '6000.00', ['Service charge' => '600.00', 'VAT' => '990.00'], '1590.00'),
        charge('ROOM', 'room', '6000.00', ['Service charge' => '600.00', 'VAT' => '990.00'], '1590.00'),
        charge('SPA', 'extra', '2000.00', ['VAT' => '300.00'], '300.00'),
    ]);

    expect($built['revenue']['ROOM|room']['amount'])->toBe('12000.00')
        ->and($built['revenue']['SPA|extra']['amount'])->toBe('2000.00')
        ->and($built['taxes'])->toBe(['Service charge' => '1200.00', 'VAT' => '2280.00'])
        ->and($built['total'])->toBe('17480.00');
});

it('moves the meal part of a room night to food revenue', function (): void {
    $built = (new NightRevenueBuilder)->build([charge('ROOM', 'room', '6000.00', [], '0.00', '1200.00')]);

    expect($built['revenue']['ROOM|room']['amount'])->toBe('4800.00')
        ->and($built['revenue']['FNB|food_beverage']['amount'])->toBe('1200.00')
        ->and($built['total'])->toBe('6000.00');
});

it('books a tax with no breakdown as VAT', function (): void {
    $built = (new NightRevenueBuilder)->build([charge('MISC', 'misc', '100.00', [], '15.00')]);

    expect($built['taxes'])->toBe(['VAT' => '15.00'])->and($built['total'])->toBe('115.00');
});

it('nets adjustments against charges and drops what cancels out', function (): void {
    $built = (new NightRevenueBuilder)->build([charge('ROOM', 'room', '500.00'), charge('ROOM', 'room', '-500.00'), charge('MISC', 'misc', '-50.00')]);

    expect(array_keys($built['revenue']))->toBe(['MISC|misc'])
        ->and($built['revenue']['MISC|misc']['amount'])->toBe('-50.00')
        ->and($built['total'])->toBe('-50.00');
});
