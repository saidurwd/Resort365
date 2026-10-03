<?php

namespace Modules\Rates\Actions;

use App\Support\Actions\Action;
use Modules\Rates\Models\RatePlan;

/**
 * Creates or updates a rate plan (validated by SaveRatePlanRequest).
 */
class SaveRatePlan extends Action
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(?RatePlan $plan, array $data): RatePlan
    {
        $plan ??= new RatePlan;
        $plan->fill($data)->save();

        return $plan;
    }
}
