<?php

/*
| Step 1.3 "Done when": tax calculator unit tests cover inclusive, exclusive and compound cases.
*/

use Modules\Core\DTOs\TaxBreakdown;
use Modules\Core\DTOs\TaxLine;
use Modules\Core\DTOs\TaxRule;
use Modules\Core\Enums\TaxType;
use Modules\Core\Services\TaxCalculator;

function serviceCharge(string $rate = '10'): TaxRule
{
    return new TaxRule('SC', 'Service charge', TaxType::Percent, $rate);
}

function vat(bool $compound = true, string $rate = '15'): TaxRule
{
    return new TaxRule('VAT', 'VAT', TaxType::Percent, $rate, $compound);
}

/**
 * @return array<string, string>
 */
function taxAmounts(TaxBreakdown $breakdown): array
{
    return array_column(array_map(fn (TaxLine $line): array => ['code' => $line->code, 'amount' => $line->amount], $breakdown->taxes), 'amount', 'code');
}

it('adds exclusive taxes: 10% service charge, then 15% VAT on the sum', function (): void {
    $result = new TaxCalculator()->calculate('1000', [serviceCharge(), vat()]);

    expect($result->net)->toBe('1000.00')
        ->and(taxAmounts($result))->toBe(['SC' => '100.00', 'VAT' => '165.00'])
        ->and($result->taxTotal)->toBe('265.00')
        ->and($result->gross)->toBe('1265.00');
});

it('calculates a non-compound tax on the net amount only', function (): void {
    $result = new TaxCalculator()->calculate('1000', [serviceCharge(), vat(compound: false)]);

    expect(taxAmounts($result))->toBe(['SC' => '100.00', 'VAT' => '150.00'])
        ->and($result->gross)->toBe('1250.00');
});

it('backs the net amount out of an inclusive price', function (): void {
    $result = new TaxCalculator()->calculate('1265', [serviceCharge(), vat()], inclusive: true);

    expect($result->net)->toBe('1000.00')
        ->and(taxAmounts($result))->toBe(['SC' => '100.00', 'VAT' => '165.00'])
        ->and($result->gross)->toBe('1265.00');
});

it('keeps inclusive prices exact when the taxes need rounding', function (string $price): void {
    $result = new TaxCalculator()->calculate($price, [serviceCharge(), vat()], inclusive: true);
    $sum = bcadd($result->net, $result->taxTotal, 2);

    expect($result->gross)->toBe(bcadd($price, '0', 2))
        ->and($sum)->toBe($result->gross)
        ->and(bcadd(...array_values(taxAmounts($result))))->toBe(bcadd($result->taxTotal, '0', 0));
})->with(['999.99', '5000', '1', '0.05', '12345.67', '7777.77']);

it('rounds each tax line half up to 2 places', function (): void {
    // 10% of 33.35 = 3.335 → 3.34; 15% of 36.69 = 5.5035 → 5.50
    $result = new TaxCalculator()->calculate('33.35', [serviceCharge(), vat()]);

    expect(taxAmounts($result))->toBe(['SC' => '3.34', 'VAT' => '5.50'])
        ->and($result->gross)->toBe('42.19');
});

it('multiplies a fixed tax by the quantity, and compounds on top of it', function (): void {
    $levy = new TaxRule('LEVY', 'Tourism levy', TaxType::Fixed, '200');
    $result = new TaxCalculator()->calculate('9000', [$levy, vat()], quantity: 3);

    expect(taxAmounts($result))->toBe(['LEVY' => '600.00', 'VAT' => '1440.00'])
        ->and($result->gross)->toBe('11040.00');
});

it('backs out an inclusive price with a fixed tax', function (): void {
    $levy = new TaxRule('LEVY', 'Tourism levy', TaxType::Fixed, '200');
    $result = new TaxCalculator()->calculate('11040', [$levy, vat()], inclusive: true, quantity: 3);

    expect($result->net)->toBe('9000.00')->and(taxAmounts($result))->toBe(['LEVY' => '600.00', 'VAT' => '1440.00']);
});

it('refuses an inclusive price lower than its fixed taxes', function (): void {
    new TaxCalculator()->calculate('100', [new TaxRule('LEVY', 'Levy', TaxType::Fixed, '200')], inclusive: true);
})->throws(DomainException::class);

it('returns the amount unchanged without taxes', function (bool $inclusive): void {
    $result = new TaxCalculator()->calculate('1500.5', [], inclusive: $inclusive);

    expect($result->net)->toBe('1500.50')->and($result->taxes)->toBe([])->and($result->gross)->toBe('1500.50');
})->with([true, false]);

it('handles a zero rate and negative amounts (refunds) symmetrically', function (): void {
    expect(new TaxCalculator()->calculate('1000', [serviceCharge('0'), vat()])->gross)->toBe('1150.00')
        ->and(new TaxCalculator()->calculate('-1000', [serviceCharge(), vat()])->gross)->toBe('-1265.00');
});
