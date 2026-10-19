<?php

namespace Modules\HR\Actions;

use App\Support\Actions\Action;
use Carbon\CarbonImmutable;
use Modules\Core\Contracts\ApprovalEngine;
use Modules\Core\Exceptions\ApprovalRuleViolated;
use Modules\HR\Enums\LeaveStatus;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Services\LeaveApprover;
use Modules\Property\Contracts\PropertyDirectory;

/**
 * Withdraws a request that is waiting for approval, or cancels an approved leave that has not started (Step 6.3): the
 * days go back on the balance and the roster gets its shifts back. Leave that has started cannot be cancelled.
 */
class CancelLeaveRequest extends Action
{
    public function __construct(
        private readonly LeaveApprover $approver,
        private readonly ApprovalEngine $approvals,
        private readonly PropertyDirectory $properties,
    ) {}

    /**
     * @throws HrRuleViolated
     */
    public function handle(LeaveRequest $request): LeaveRequest
    {
        if (! in_array($request->status, [LeaveStatus::Pending, LeaveStatus::Approved], true)) {
            throw new HrRuleViolated(__('Request :no is :status.', ['no' => $request->request_no, 'status' => mb_strtolower($request->status->label())]));
        }

        $today = CarbonImmutable::parse($this->properties->find($request->property_id)->businessDate ?? now()->toDateString());

        if ($request->status === LeaveStatus::Approved && $request->from_date->lte($today)) {
            throw new HrRuleViolated(__('This leave has started, so it can no longer be cancelled.'));
        }

        return $this->transaction(function () use ($request): LeaveRequest {
            if ($request->status === LeaveStatus::Pending && $request->approval_request_id !== null) {
                try {
                    $this->approvals->cancel($request->approval_request_id, $request->requested_by);
                } catch (ApprovalRuleViolated) {
                    // The approval was decided at the same moment; the approver below sees the leave's real state.
                }
            }

            $this->approver->cancel($request);

            return $request->refresh();
        });
    }
}
