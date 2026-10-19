<?php

namespace Modules\HR\Actions;

use App\Support\Actions\Action;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Models\LeaveBalance;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Models\LeaveType;

/**
 * Deletes a leave type nobody has a balance or request for (Step 6.3); otherwise it can only be made inactive.
 */
class DeleteLeaveType extends Action
{
    /**
     * @throws HrRuleViolated
     */
    public function handle(LeaveType $type): void
    {
        if (LeaveRequest::query()->where('leave_type_id', $type->id)->exists() || LeaveBalance::query()->where('leave_type_id', $type->id)->exists()) {
            throw new HrRuleViolated(__('":name" has balances or requests, so it can only be made inactive.', ['name' => $type->name]));
        }

        $type->policy()->delete();
        $type->delete();
    }
}
