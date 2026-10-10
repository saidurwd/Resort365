<?php

namespace Modules\Accounting\Services\Statements;

use Modules\Accounting\DTOs\AccountActivity;

/**
 * The trial balance (Step 4.5): per postable account its opening balance, the period's debits and credits and the
 * closing balance, each as a debit or credit figure; accounts without figures are left out. Debits and credits
 * of the period, and of the closing balances, must agree. Pure.
 */
class TrialBalanceBuilder
{
    /**
     * @param  list<AccountActivity>  $accounts
     * @return array{rows: list<array{account: AccountActivity, opening_debit: string, opening_credit: string, debit: string, credit: string, closing_debit: string, closing_credit: string}>, totals: array<string, string>, balanced: bool}
     */
    public function build(array $accounts): array
    {
        $rows = [];
        $totals = array_fill_keys(['opening_debit', 'opening_credit', 'debit', 'credit', 'closing_debit', 'closing_credit'], '0.00');

        foreach ($accounts as $account) {
            if ($account->isGroup) {
                continue;
            }

            $closing = $account->closing();

            if (bccomp($account->opening, '0', 2) === 0 && bccomp($account->debit, '0', 2) === 0 && bccomp($account->credit, '0', 2) === 0 && bccomp($closing, '0', 2) === 0) {
                continue;
            }

            $row = [
                'account' => $account, 'opening_debit' => $this->side($account->opening, true), 'opening_credit' => $this->side($account->opening, false), 'debit' => $account->debit, 'credit' => $account->credit,
                'closing_debit' => $this->side($closing, true), 'closing_credit' => $this->side($closing, false),
            ];

            foreach (array_keys($totals) as $key) {
                $totals[$key] = bcadd($totals[$key], $row[$key], 2);
            }

            $rows[] = $row;
        }

        return ['rows' => $rows, 'totals' => $totals, 'balanced' => bccomp($totals['debit'], $totals['credit'], 2) === 0 && bccomp($totals['closing_debit'], $totals['closing_credit'], 2) === 0 && bccomp($totals['opening_debit'], $totals['opening_credit'], 2) === 0];
    }

    private function side(string $net, bool $debit): string
    {
        return $debit ? (bccomp($net, '0', 2) > 0 ? $net : '0.00') : (bccomp($net, '0', 2) < 0 ? ltrim($net, '-') : '0.00');
    }
}
