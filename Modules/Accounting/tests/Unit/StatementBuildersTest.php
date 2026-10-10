<?php

use Modules\Accounting\DTOs\AccountActivity;
use Modules\Accounting\Enums\AccountType;
use Modules\Accounting\Services\Statements\CashFlowBuilder;
use Modules\Accounting\Services\Statements\DepartmentalReport;
use Modules\Accounting\Services\Statements\LedgerAging;
use Modules\Accounting\Services\Statements\StatementTree;
use Modules\Accounting\Services\Statements\TrialBalanceBuilder;

/**
 * @param  array<string, string>  $columns
 */
function act(int $id, string $code, AccountType $type, ?int $parent = null, bool $group = false, string $opening = '0.00', string $debit = '0.00', string $credit = '0.00', ?string $usali = null, array $columns = []): AccountActivity
{
    return new AccountActivity($id, $code, 'Account '.$code, $type, $parent, $group, $usali, $opening, $debit, $credit, $columns);
}

it('rolls the tree up and drops rows that are zero everywhere', function (): void {
    $accounts = [act(1, '4000', AccountType::Income, null, true), act(2, '4100', AccountType::Income, 1), act(3, '4200', AccountType::Income, 1), act(4, '4300', AccountType::Income, 1)];
    $tree = new StatementTree;
    $rows = $tree->rows($accounts, [2 => ['total' => '100.00', 'a' => '60.00'], 3 => ['total' => '50.50', 'a' => '0.00'], 4 => ['total' => '0.00', 'a' => '0.00']], ['total', 'a']);

    expect(array_map(fn (array $row): array => [$row['account']->code, $row['depth'], $row['amounts']['total'], $row['amounts']['a']], $rows))
        ->toBe([['4000', 0, '150.50', '60.00'], ['4100', 1, '100.00', '60.00'], ['4200', 1, '50.50', '0.00']])
        ->and($tree->totals($rows, ['total', 'a']))->toBe(['total' => '150.50', 'a' => '60.00']);
});

it('builds a trial balance that balances, leaving out empty accounts', function (): void {
    $built = (new TrialBalanceBuilder)->build([
        act(1, '1000', AccountType::Asset, null, true),
        act(2, '1110', AccountType::Asset, 1, false, '100.00', '50.00', '30.00'),
        act(3, '3100', AccountType::Equity, null, false, '-100.00', '0.00', '20.00'),
        act(4, '4110', AccountType::Income, null, false, '0.00', '0.00', '0.00'),
        act(5, '6110', AccountType::Expense, null, false, '0.00', '0.00', '0.00'),
    ]);

    expect(array_map(fn (array $row): string => $row['account']->code, $built['rows']))->toBe(['1110', '3100'])
        ->and($built['rows'][0])->toMatchArray(['opening_debit' => '100.00', 'opening_credit' => '0.00', 'closing_debit' => '120.00', 'closing_credit' => '0.00'])
        ->and($built['rows'][1])->toMatchArray(['opening_credit' => '100.00', 'closing_credit' => '120.00'])
        ->and($built['totals']['opening_debit'])->toBe('100.00')->and($built['totals']['opening_credit'])->toBe('100.00');
});

it('flags a trial balance whose debits and credits differ', function (): void {
    expect((new TrialBalanceBuilder)->build([act(1, '1110', AccountType::Asset, null, false, '0.00', '100.00', '0.00')])['balanced'])->toBeFalse();
});

it('builds a cash flow whose total equals the change in cash', function (): void {
    $built = (new CashFlowBuilder)->build([
        act(1, '1110', AccountType::Asset, null, false, '1000.00', '700.00', '200.00'),
        act(2, '1210', AccountType::Asset, null, false, '0.00', '300.00', '100.00'),
        act(3, '1520', AccountType::Asset, null, false, '0.00', '400.00', '0.00'),
        act(4, '2110', AccountType::Liability, null, false, '0.00', '0.00', '250.00'),
        act(5, '2510', AccountType::Liability, null, false, '0.00', '0.00', '600.00'),
        act(6, '3100', AccountType::Equity, null, false, '0.00', '0.00', '100.00'),
        act(7, '4110', AccountType::Income, null, false, '0.00', '0.00', '500.00'),
        act(8, '6110', AccountType::Expense, null, false, '0.00', '350.00', '0.00'),
    ]);

    // Net result 150; receivables −200, customer advances +250 → operating 200; investing −400; financing 700.
    expect($built['result'])->toBe('150.00')->and($built['totals'])->toBe(['operating' => '200.00', 'investing' => '-400.00', 'financing' => '700.00', 'change' => '500.00'])
        ->and($built['opening_cash'])->toBe('1000.00')->and($built['closing_cash'])->toBe('1500.00')->and($built['difference'])->toBe('0.00');
});

it('adds revenue and expense per operated department, the rest as undistributed', function (): void {
    $built = (new DepartmentalReport)->build([
        act(1, '4110', AccountType::Income, null, false, '0.00', '0.00', '1000.00', 'rooms'),
        act(2, '4210', AccountType::Income, null, false, '0.00', '0.00', '400.00', 'fnb'),
        act(3, '5110', AccountType::Expense, null, false, '0.00', '300.00', '0.00', 'rooms'),
        act(4, '6110', AccountType::Expense, null, false, '0.00', '200.00', '0.00', 'admin'),
        act(5, '6210', AccountType::Expense, null, false, '0.00', '50.00', '0.00'),
    ]);

    expect($built['departments']['rooms'])->toBe(['revenue' => '1000.00', 'expense' => '300.00', 'profit' => '700.00'])
        ->and($built['departments']['fnb']['profit'])->toBe('400.00')
        ->and($built['undistributed'])->toBe(['admin' => '200.00', 'other' => '50.00'])
        ->and($built['gross_operating_profit'])->toBe('850.00');
});

it('ages charges oldest first, applying payments to the oldest', function (): void {
    $aged = (new LedgerAging)->build([
        ['party' => 'Acme', 'date' => '2026-06-01', 'amount' => '1000.00'],
        ['party' => 'Acme', 'date' => '2026-09-01', 'amount' => '500.00'],
        ['party' => 'Acme', 'date' => '2026-10-05', 'amount' => '200.00'],
        ['party' => 'Acme', 'date' => '2026-10-06', 'amount' => '-1200.00'],
        ['party' => 'Beta', 'date' => '2026-10-01', 'amount' => '300.00'],
        ['party' => 'Beta', 'date' => '2026-10-02', 'amount' => '-400.00'],
        ['party' => 'Gamma', 'date' => '2026-10-01', 'amount' => '100.00'],
        ['party' => 'Gamma', 'date' => '2026-10-02', 'amount' => '-100.00'],
    ], '2026-10-31');

    expect($aged['Acme'])->toBe(['current' => '200.00', 'days_31_60' => '300.00', 'days_61_90' => '0.00', 'over_90' => '0.00', 'credit' => '0.00', 'total' => '500.00'])
        ->and($aged['Beta']['credit'])->toBe('100.00')->and($aged['Beta']['total'])->toBe('-100.00')
        ->and($aged)->not->toHaveKey('Gamma');
});
