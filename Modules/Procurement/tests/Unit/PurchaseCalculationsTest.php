<?php

/*
| The pure arithmetic of Procurement (Step 5.5): a line's net amount and the document totals, and the comparison of
| vendors' quotations.
*/
use Modules\Procurement\Services\PurchaseTotals;
use Modules\Procurement\Services\QuotationComparer;

it('rounds a line once, half up, and adds the lines exactly', function (): void {
    $totals = new PurchaseTotals;

    expect($totals->net('3', '10.005'))->toBe('30.02')->and($totals->net('0.5', '3'))->toBe('1.50')->and($totals->net('12.5', '14.5'))->toBe('181.25')
        ->and($totals->summarise([['net' => '100.10', 'tax' => '15.02'], ['net' => '200.20', 'tax' => '30.03']]))->toBe(['subtotal' => '300.30', 'tax_total' => '45.05', 'total' => '345.35'])
        ->and($totals->summarise([]))->toBe(['subtotal' => '0.00', 'tax_total' => '0.00', 'total' => '0.00']);
});

it('totals each quotation at the quantities asked and finds the cheapest complete one', function (): void {
    $asked = [1 => '40', 2 => '20', 3 => '10'];
    $quotes = [
        10 => [1 => '275', 2 => '1850', 3 => '1550'],
        11 => [1 => '290', 2 => '1790', 3 => '1580'],
        12 => [1 => '200', 2 => '1700'],
    ];

    $result = (new QuotationComparer)->compare($asked, $quotes);

    expect($result['totals'])->toBe([10 => '63500.00', 11 => '63200.00', 12 => '42000.00'])
        ->and($result['missing'])->toBe([10 => [], 11 => [], 12 => [3]])
        ->and($result['cheapest'])->toBe(11)
        ->and($result['best_price'])->toBe([1 => 12, 2 => 12, 3 => 10]);
});

it('has no cheapest quotation when none quotes everything', function (): void {
    $result = (new QuotationComparer)->compare([1 => '5', 2 => '5'], [10 => [1 => '10'], 11 => [2 => '10']]);

    expect($result['cheapest'])->toBeNull()->and($result['totals'])->toBe([10 => '50.00', 11 => '50.00']);
    expect((new QuotationComparer)->compare([1 => '5'], []))->toBe(['totals' => [], 'missing' => [], 'cheapest' => null, 'best_price' => []]);
});
