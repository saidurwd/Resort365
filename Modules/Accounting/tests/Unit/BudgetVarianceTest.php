<?php

/*
| Budget against actual (Step 7.2): a variance is favourable when positive, for income and for expense. Pure.
*/

use Modules\Accounting\Services\BudgetKey;
use Modules\Accounting\Services\BudgetVariance;

it('is favourable when income beats the budget', function (): void {
    expect((new BudgetVariance)->of('income', '100000.00', '112500.00'))->toBe(['amount' => '12500.00', 'percent' => '12.5'])
        ->and((new BudgetVariance)->of('income', '100000.00', '90000.00'))->toBe(['amount' => '-10000.00', 'percent' => '-10.0']);
});

it('is favourable when an expense stays under the budget', function (): void {
    expect((new BudgetVariance)->of('expense', '50000.00', '45000.00'))->toBe(['amount' => '5000.00', 'percent' => '10.0'])
        ->and((new BudgetVariance)->of('expense', '50000.00', '53000.00'))->toBe(['amount' => '-3000.00', 'percent' => '-6.0']);
});

it('has no percent when nothing was budgeted', function (): void {
    expect((new BudgetVariance)->of('expense', '0.00', '800.00'))->toBe(['amount' => '-800.00', 'percent' => null])
        ->and((new BudgetVariance)->of('income', '0', '0'))->toBe(['amount' => '0.00', 'percent' => null]);
});

it('adds a run of months', function (): void {
    $months = [1 => '100.00', 2 => '200.50', 3 => '300.00', 12 => '50.00'];

    expect((new BudgetVariance)->sum($months, 1, 3))->toBe('600.50')->and((new BudgetVariance)->sum($months, 2, 12))->toBe('550.50')->and((new BudgetVariance)->sum([], 1, 12))->toBe('0.00');
});

it('keys an account and a department and splits the key again', function (): void {
    expect(BudgetKey::of(7, 3))->toBe('7:3')->and(BudgetKey::of(7, null))->toBe('7:0')->and(BudgetKey::split('7:3'))->toBe([7, 3])->and(BudgetKey::split('7:0'))->toBe([7, null]);
});
