<?php

namespace Modules\Accounting\Services\Statements;

use Modules\Accounting\DTOs\AccountActivity;
use Modules\Accounting\Enums\AccountType;

/**
 * The cash flow statement by the indirect method (Step 4.5). Net result of the period, plus the change in every
 * account that is not cash: operating (receivables, inventory, prepayments, payables, taxes, payroll), investing
 * (property and equipment, codes 15xx) and financing (loans 25xx and equity 3xxx). The cash accounts are the
 * group 11xx. In a balanced ledger the total equals the change in cash, which the builder checks. Pure.
 */
class CashFlowBuilder
{
    private const string CASH = '11';

    private const array INVESTING = ['15'];

    private const array FINANCING = ['25', '3'];

    /**
     * @param  list<AccountActivity>  $accounts
     * @return array{result: string, operating: list<array{account: AccountActivity, amount: string}>, investing: list<array{account: AccountActivity, amount: string}>, financing: list<array{account: AccountActivity, amount: string}>, totals: array{operating: string, investing: string, financing: string, change: string}, opening_cash: string, closing_cash: string, difference: string}
     */
    public function build(array $accounts): array
    {
        $result = '0.00';
        $sections = ['operating' => [], 'investing' => [], 'financing' => []];
        $openingCash = '0.00';
        $closingCash = '0.00';

        foreach ($accounts as $account) {
            if ($account->isGroup) {
                continue;
            }

            if ($account->type === AccountType::Income || $account->type === AccountType::Expense) {
                $result = bcsub($result, $account->net(), 2);

                continue;
            }

            if ($this->startsWith($account->code, [self::CASH])) {
                $openingCash = bcadd($openingCash, $account->opening, 2);
                $closingCash = bcadd($closingCash, $account->closing(), 2);

                continue;
            }

            // Cash effect of an account's change: an asset rising uses cash, a liability or equity rising brings it.
            $amount = bcsub('0', $account->net(), 2);

            if (bccomp($amount, '0', 2) === 0) {
                continue;
            }

            $section = $this->startsWith($account->code, self::INVESTING) ? 'investing' : ($this->startsWith($account->code, self::FINANCING) ? 'financing' : 'operating');
            $sections[$section][] = ['account' => $account, 'amount' => $amount];
        }

        $sum = fn (array $rows): string => array_reduce($rows, fn (string $total, array $row): string => bcadd($total, $row['amount'], 2), '0.00');
        $totals = ['operating' => bcadd($result, $sum($sections['operating']), 2), 'investing' => $sum($sections['investing']), 'financing' => $sum($sections['financing'])];
        $totals['change'] = bcadd(bcadd($totals['operating'], $totals['investing'], 2), $totals['financing'], 2);

        return [
            'result' => $result, ...$sections, 'totals' => $totals, 'opening_cash' => $openingCash, 'closing_cash' => $closingCash,
            'difference' => bcsub(bcsub($closingCash, $openingCash, 2), $totals['change'], 2),
        ];
    }

    /**
     * @param  list<string>  $prefixes
     */
    private function startsWith(string $code, array $prefixes): bool
    {
        return array_any($prefixes, fn (string $prefix): bool => str_starts_with($code, $prefix));
    }
}
