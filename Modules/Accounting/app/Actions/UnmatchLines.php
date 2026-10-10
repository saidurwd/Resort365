<?php

namespace Modules\Accounting\Actions;

use App\Support\Actions\Action;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Models\BankMatch;
use Modules\Accounting\Services\ChequeTracker;

/**
 * Undoes a match before the reconciliation is completed; a cheque it cleared is pending again.
 */
class UnmatchLines extends Action
{
    public function __construct(private readonly ChequeTracker $cheques) {}

    /**
     * @throws AccountingRuleViolated
     */
    public function handle(BankMatch $match): void
    {
        $this->transaction(function () use ($match): void {
            $locked = BankMatch::query()->with('journalLine')->lockForUpdate()->findOrFail($match->id);

            if ($locked->isLocked()) {
                throw new AccountingRuleViolated(__('This match belongs to a completed reconciliation.'));
            }

            $this->cheques->pending($locked->journalLine->journal_entry_id);
            $locked->delete();
        });
    }
}
