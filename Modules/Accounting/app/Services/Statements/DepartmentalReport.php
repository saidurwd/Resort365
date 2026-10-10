<?php

namespace Modules\Accounting\Services\Statements;

use Modules\Accounting\DTOs\AccountActivity;
use Modules\Accounting\Enums\AccountType;

/**
 * The USALI departmental report (Step 4.5): revenue, expense and profit per operated department (rooms, food and
 * beverage, other operated), then the undistributed expenses and the total. A department is the account's USALI
 * tag; accounts without one count as undistributed. Pure; amounts are for display (revenue and profit positive).
 */
class DepartmentalReport
{
    public const array OPERATED = ['rooms', 'fnb', 'other_operated'];

    /**
     * @param  list<AccountActivity>  $accounts
     * @return array{departments: array<string, array{revenue: string, expense: string, profit: string}>, undistributed: array<string, string>, undistributed_total: string, income_total: string, gross_operating_profit: string}
     */
    public function build(array $accounts): array
    {
        $departments = array_fill_keys(self::OPERATED, ['revenue' => '0.00', 'expense' => '0.00', 'profit' => '0.00']);
        $undistributed = [];
        $income = '0.00';

        foreach ($accounts as $account) {
            if ($account->isGroup || ($account->type !== AccountType::Income && $account->type !== AccountType::Expense)) {
                continue;
            }

            $net = $account->net();

            if ($account->type === AccountType::Income) {
                $amount = bcsub('0', $net, 2);
                $income = bcadd($income, $amount, 2);

                if (isset($departments[(string) $account->usali])) {
                    $departments[$account->usali]['revenue'] = bcadd($departments[$account->usali]['revenue'], $amount, 2);
                }

                continue;
            }

            if (isset($departments[(string) $account->usali])) {
                $departments[$account->usali]['expense'] = bcadd($departments[$account->usali]['expense'], $net, 2);
            } else {
                $key = $account->usali ?? 'other';
                $undistributed[$key] = bcadd($undistributed[$key] ?? '0.00', $net, 2);
            }
        }

        $profit = '0.00';

        foreach ($departments as $key => $row) {
            $departments[$key]['profit'] = bcsub($row['revenue'], $row['expense'], 2);
            $profit = bcadd($profit, $departments[$key]['profit'], 2);
        }

        $undistributed = array_filter($undistributed, fn (string $amount): bool => bccomp($amount, '0', 2) !== 0);
        $undistributedTotal = array_reduce($undistributed, fn (string $sum, string $amount): string => bcadd($sum, $amount, 2), '0.00');
        ksort($undistributed);

        return ['departments' => $departments, 'undistributed' => $undistributed, 'undistributed_total' => $undistributedTotal, 'income_total' => $income, 'gross_operating_profit' => bcsub($profit, $undistributedTotal, 2)];
    }
}
