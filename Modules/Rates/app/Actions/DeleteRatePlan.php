<?php

namespace Modules\Rates\Actions;

use App\Support\Actions\Action;
use Modules\Rates\Contracts\RatePlanUsage;
use Modules\Rates\Exceptions\RatePlanInUse;
use Modules\Rates\Models\RatePlan;

/**
 * Deletes (soft) a rate plan; bookings made with it keep their prices. A plan that bookings which
 * have not ended yet still use cannot be deleted (deactivate it instead).
 */
class DeleteRatePlan extends Action
{
    public function __construct(private readonly RatePlanUsage $usage) {}

    /**
     * @throws RatePlanInUse
     */
    public function handle(RatePlan $plan): void
    {
        if ($this->usage->hasFutureBookings($plan->id)) {
            throw new RatePlanInUse(__('Rate plan ":name" is used by upcoming bookings. Deactivate it instead.', ['name' => $plan->name]));
        }

        $plan->delete();
    }
}
