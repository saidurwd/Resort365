<?php

namespace Modules\Accounting\Actions;

use App\Support\Actions\Action;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Models\BankMatch;
use Modules\Accounting\Models\BankReconciliation;
use Modules\Accounting\Models\BankStatement;
use Modules\Accounting\Models\BankStatementLine;
use Modules\Accounting\Services\ReconciliationView;

/**
 * Completes a statement's reconciliation (Step 4.4): every line matched and the difference zero. The figures
 * are kept as they stand and the matches are locked.
 */
class CompleteReconciliation extends Action
{
    public function __construct(private readonly ReconciliationView $view) {}

    /**
     * @throws AccountingRuleViolated
     */
    public function handle(BankStatement $statement, ?int $userId = null): BankReconciliation
    {
        return $this->transaction(function () use ($statement, $userId): BankReconciliation {
            $locked = BankStatement::query()->lockForUpdate()->findOrFail($statement->id);
            $view = $this->view->for($locked);

            if ($view['complete']) {
                throw new AccountingRuleViolated(__('This statement is already reconciled.'));
            }

            $unmatched = $view['lines']->filter(fn (BankStatementLine $line): bool => $line->match === null)->count();

            if ($unmatched > 0) {
                throw new AccountingRuleViolated(trans_choice(':count statement line is not matched yet.|:count statement lines are not matched yet.', $unmatched));
            }

            if ($view['figures']['difference'] !== '0.00') {
                throw new AccountingRuleViolated(__('The books and the bank still differ by :amount.', ['amount' => $view['figures']['difference']]));
            }

            $reconciliation = BankReconciliation::query()->create([
                'bank_statement_id' => $locked->id, 'bank_account_id' => $locked->bank_account_id, 'statement_date' => $locked->statement_to->toDateString(),
                'statement_balance' => $view['figures']['statement_balance'], 'book_balance' => $view['figures']['book_balance'], 'outstanding_net' => $view['figures']['outstanding_net'],
                'difference' => $view['figures']['difference'], 'completed_by' => $userId, 'completed_at' => now(),
            ]);
            BankMatch::query()->whereIn('bank_statement_line_id', $view['lines']->pluck('id'))->update(['bank_reconciliation_id' => $reconciliation->id]);

            return $reconciliation;
        });
    }
}
