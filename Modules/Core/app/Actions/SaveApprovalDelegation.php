<?php

namespace Modules\Core\Actions;

use App\Support\Actions\Action;
use Modules\Core\Exceptions\ApprovalRuleViolated;
use Modules\Core\Models\ApprovalDelegation;

/**
 * Lets another person decide in your place between two dates (Step 5.1). A delegate cannot approve what they asked
 * for themselves, and a delegator's own request is not decided by their delegate either.
 */
class SaveApprovalDelegation extends Action
{
    /**
     * @throws ApprovalRuleViolated
     */
    public function handle(int $userId, int $delegateUserId, string $from, string $to): ApprovalDelegation
    {
        if ($userId === $delegateUserId) {
            throw new ApprovalRuleViolated(__('Choose someone else as your delegate.'));
        }

        if ($to < $from) {
            throw new ApprovalRuleViolated(__('The delegation cannot end before it starts.'));
        }

        return ApprovalDelegation::query()->create(['user_id' => $userId, 'delegate_user_id' => $delegateUserId, 'starts_on' => $from, 'ends_on' => $to]);
    }
}
