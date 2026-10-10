<?php

namespace Modules\Accounting\Actions;

use App\Support\Actions\Action;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Models\JournalEntry;

/**
 * Throws a draft entry away. A posted entry is never deleted (the model refuses): reverse it.
 */
class DiscardJournalDraft extends Action
{
    /**
     * @throws AccountingRuleViolated
     */
    public function handle(JournalEntry $entry): void
    {
        $this->transaction(function () use ($entry): void {
            $locked = JournalEntry::query()->lockForUpdate()->findOrFail($entry->id);

            if (! $locked->isDraft()) {
                throw new AccountingRuleViolated(__('Only a draft can be discarded: reverse a posted entry instead.'));
            }

            $locked->lines()->delete();
            $locked->delete();
        });
    }
}
