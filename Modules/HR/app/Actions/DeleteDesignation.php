<?php

namespace Modules\HR\Actions;

use App\Support\Actions\Action;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Models\Designation;

/**
 * Deletes a designation nobody holds (Step 6.1); otherwise it can only be made inactive.
 */
class DeleteDesignation extends Action
{
    /**
     * @throws HrRuleViolated
     */
    public function handle(Designation $designation): void
    {
        if ($designation->employees()->withTrashed()->exists()) {
            throw new HrRuleViolated(__('":name" is held by employees, so it can only be made inactive.', ['name' => $designation->name]));
        }

        $designation->delete();
    }
}
