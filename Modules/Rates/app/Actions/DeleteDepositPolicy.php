<?php

namespace Modules\Rates\Actions;

use App\Support\Actions\Action;
use Modules\Rates\Exceptions\PolicyInUse;
use Modules\Rates\Models\DepositPolicy;
use Modules\Rates\Models\RatePlan;

/**
 * Deletes (soft) a policy that no rate plan uses.
 */
class DeleteDepositPolicy extends Action
{
    /**
     * @throws PolicyInUse
     */
    public function handle(DepositPolicy $depositPolicy): void
    {
        $plans = RatePlan::query()->where('deposit_policy_id', $depositPolicy->id)->pluck('name');

        if ($plans->isNotEmpty()) {
            throw new PolicyInUse(__('":policy" is used by :plans. Choose another policy for them first.', [
                'policy' => $depositPolicy->name, 'plans' => $plans->implode(', '),
            ]));
        }

        $depositPolicy->delete();
    }
}
