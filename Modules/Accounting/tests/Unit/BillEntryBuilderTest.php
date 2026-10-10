<?php

use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Services\BillEntryBuilder;
use Modules\Restaurant\DTOs\BillFact;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @param  array<string, string>  $payments
 * @param  array<string, string>  $taxes
 */
function billFact(array $payments, string $food = '200.00', string $beverage = '350.00', string $service = '55.00', array $taxes = ['VAT' => '90.75'], string $tips = '0.00', bool $complimentary = false): BillFact
{
    return new BillFact(1, 'MR-B000001', 1, 1, 'Main Restaurant', '2026-10-10', ['food' => $food, 'beverage' => $beverage], $service, $taxes, '695.75', $tips, $payments, $complimentary, false);
}

it('splits a paid bill into tenders, revenue, service charge, taxes and tips', function (): void {
    $built = (new BillEntryBuilder)->build(billFact(['card' => '745.75'], tips: '50.00'));

    expect($built)->toBe(['tenders' => ['card' => '745.75'], 'revenue' => ['food' => '200.00', 'beverage' => '350.00'], 'service' => '55.00', 'taxes' => ['VAT' => '90.75'], 'tips' => '50.00']);
});

it('keeps each tender of a part-paid bill', function (): void {
    $built = (new BillEntryBuilder)->build(billFact(['cash' => '200.00', 'room_charge' => '495.75']));

    expect($built['tenders'])->toBe(['cash' => '200.00', 'room_charge' => '495.75']);
});

it('posts nothing for a complimentary bill or a meal-plan tender', function (): void {
    expect((new BillEntryBuilder)->build(billFact(['complimentary' => '695.75'], complimentary: true)))->toBeNull()
        ->and((new BillEntryBuilder)->build(billFact(['package' => '0.00'])))->toBeNull();
});

it('puts a rounding difference on the larger revenue class', function (): void {
    $built = (new BillEntryBuilder)->build(billFact(['cash' => '695.76']));

    expect($built['revenue'])->toBe(['food' => '200.00', 'beverage' => '350.01']);
});

it('refuses a bill whose payments and lines are far apart', function (): void {
    (new BillEntryBuilder)->build(billFact(['cash' => '700.00']));
})->throws(AccountingRuleViolated::class);
