<?php

namespace Modules\Accounting\Actions;

use App\Support\Actions\Action;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Models\Account;

/**
 * Deletes an account nothing uses: not a system account (automatic postings need it), not one that holds
 * other accounts, not one with postings (deactivate it instead).
 */
class DeleteAccount extends Action
{
    /**
     * @throws AccountingRuleViolated
     */
    public function handle(Account $account): void
    {
        if ($account->system_key !== null) {
            throw new AccountingRuleViolated(__(':account is a system account (automatic postings use it): deactivate it, or map the posting to another account.', ['account' => $account->label()]));
        }

        if ($account->children()->exists()) {
            throw new AccountingRuleViolated(__(':account holds other accounts: move or delete them first.', ['account' => $account->label()]));
        }

        if ($account->lines()->exists()) {
            throw new AccountingRuleViolated(__(':account has postings: deactivate it instead.', ['account' => $account->label()]));
        }

        $account->delete();
    }
}
