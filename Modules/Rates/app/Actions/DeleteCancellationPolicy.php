<?php

namespace Modules\Rates\Actions;

use App\Support\Actions\Action;
use Modules\Rates\Exceptions\PolicyInUse;
use Modules\Rates\Models\CancellationPolicy;
use Modules\Rates\Models\RatePlan;

/**
 * Deletes (soft) a policy that no rate plan uses.
 */
class DeleteCancellationPolicy extends Action
{
    /**
     * @throws PolicyInUse
     */
    public function handle(CancellationPolicy $cancellationPolicy): void
    {
        $plans = RatePlan::query()->where('cancellation_policy_id', $cancellationPolicy->id)->pluck('name');

        if ($plans->isNotEmpty()) {
            throw new PolicyInUse(__('":policy" is used by :plans. Choose another policy for them first.', [
                'policy' => $cancellationPolicy->name, 'plans' => $plans->implode(', '),
            ]));
        }

        $cancellationPolicy->delete();
    }
}
