<?php

namespace Modules\Accounting\Actions;

use App\Support\Actions\Action;
use Modules\Accounting\Models\Budget;
use Modules\Accounting\Models\BudgetLine;

/**
 * Deletes a budget with its lines (Step 7.2). The ledger is not touched.
 */
class DeleteBudget extends Action
{
    public function handle(Budget $budget): void
    {
        $this->transaction(function () use ($budget): void {
            BudgetLine::query()->where('budget_id', $budget->id)->delete();
            $budget->delete();
        });
    }
}
