<?php

namespace Modules\Accounting\Services\Statements;

use Modules\Accounting\DTOs\AccountActivity;

/**
 * Lays accounts out as the chart's tree with every group rolled up (Step 4.5). The caller gives the accounts of
 * one section and, per account, the amount of each column already signed for display; groups sum their children
 * and rows that are zero in every column are left out. Pure.
 */
class StatementTree
{
    /**
     * @param  list<AccountActivity>  $accounts  one section's accounts (e.g. all income accounts)
     * @param  array<int, array<string, string>>  $amounts  account id => column => amount
     * @param  list<string>  $columns
     * @return list<array{account: AccountActivity, depth: int, amounts: array<string, string>}>
     */
    public function rows(array $accounts, array $amounts, array $columns): array
    {
        $ids = array_map(fn (AccountActivity $account): int => $account->id, $accounts);
        $children = [];

        foreach ($accounts as $account) {
            $children[$account->parentId !== null && in_array($account->parentId, $ids, true) ? $account->parentId : 0][] = $account;
        }

        return $this->walk($children, 0, 0, $amounts, $columns)['rows'];
    }

    /**
     * Totals of the top-level rows per column.
     *
     * @param  list<array{account: AccountActivity, depth: int, amounts: array<string, string>}>  $rows
     * @param  list<string>  $columns
     * @return array<string, string>
     */
    public function totals(array $rows, array $columns): array
    {
        $totals = array_fill_keys($columns, '0.00');

        foreach ($rows as $row) {
            if ($row['depth'] === 0) {
                foreach ($columns as $column) {
                    $totals[$column] = bcadd($totals[$column], $row['amounts'][$column] ?? '0.00', 2);
                }
            }
        }

        return $totals;
    }

    /**
     * @param  array<int, list<AccountActivity>>  $children
     * @param  array<int, array<string, string>>  $amounts
     * @param  list<string>  $columns
     * @return array{sum: array<string, string>, rows: list<array{account: AccountActivity, depth: int, amounts: array<string, string>}>}
     */
    private function walk(array $children, int $parent, int $depth, array $amounts, array $columns): array
    {
        $sum = array_fill_keys($columns, '0.00');
        $rows = [];

        foreach ($children[$parent] ?? [] as $account) {
            $own = array_fill_keys($columns, '0.00');

            foreach ($columns as $column) {
                $own[$column] = $amounts[$account->id][$column] ?? '0.00';
            }

            $below = $this->walk($children, $account->id, $depth + 1, $amounts, $columns);

            foreach ($columns as $column) {
                $own[$column] = bcadd($own[$column], $below['sum'][$column], 2);
            }

            if (array_filter($own, fn (string $value): bool => bccomp($value, '0', 2) !== 0) === []) {
                continue;
            }

            $rows[] = ['account' => $account, 'depth' => $depth, 'amounts' => $own];
            $rows = [...$rows, ...$below['rows']];

            foreach ($columns as $column) {
                $sum[$column] = bcadd($sum[$column], $own[$column], 2);
            }
        }

        return ['sum' => $sum, 'rows' => $rows];
    }
}
