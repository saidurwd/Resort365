<?php

namespace Modules\Rates\Actions;

use App\Support\Actions\Action;
use Modules\Rates\Models\RatePlan;

/**
 * Deletes (soft) a rate plan; bookings made with it keep their prices.
 * TODO(step-1.6): refuse while future reservations use the plan.
 */
class DeleteRatePlan extends Action
{
    public function handle(RatePlan $plan): void
    {
        $plan->delete();
    }
}
