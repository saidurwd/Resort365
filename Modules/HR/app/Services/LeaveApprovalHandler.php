<?php

namespace Modules\HR\Services;

use App\Support\Tenancy\PropertyContext;
use Modules\Core\Contracts\ApprovalHandler;
use Modules\Core\DTOs\ApprovalDecision;
use Modules\HR\Models\LeaveRequest;

/**
 * Applies the decision on a leave request when its approval is finished (Step 6.3).
 */
class LeaveApprovalHandler implements ApprovalHandler
{
    public function __construct(
        private readonly LeaveApprover $approver,
        private readonly PropertyContext $properties,
    ) {}

    public function approved(ApprovalDecision $decision): void
    {
        $request = $this->find($decision);

        if ($request instanceof LeaveRequest) {
            $this->approver->approve($request);
        }
    }

    public function rejected(ApprovalDecision $decision): void
    {
        $request = $this->find($decision);

        if ($request instanceof LeaveRequest) {
            $this->approver->reject($request);
        }
    }

    private function find(ApprovalDecision $decision): ?LeaveRequest
    {
        $found = $this->properties->unrestricted(fn (): ?LeaveRequest => LeaveRequest::query()->find($decision->payload['leave_request_id'] ?? 0));

        return $found instanceof LeaveRequest ? $found : null;
    }
}
