<?php

namespace Modules\Payroll\Services;

use App\Support\Tenancy\PropertyContext;
use Modules\Core\Contracts\ApprovalHandler;
use Modules\Core\DTOs\ApprovalDecision;
use Modules\Payroll\Models\PayrollRun;

/**
 * Applies the decision on a payroll run when its approval is finished (Step 6.5): approved runs lock, rejected ones open again.
 */
class PayrollApprovalHandler implements ApprovalHandler
{
    public function __construct(
        private readonly PayrollApprover $approver,
        private readonly PropertyContext $properties,
    ) {}

    public function approved(ApprovalDecision $decision): void
    {
        $run = $this->find($decision);

        if ($run instanceof PayrollRun) {
            $this->approver->approve($run, $decision->decidedBy);
        }
    }

    public function rejected(ApprovalDecision $decision): void
    {
        $run = $this->find($decision);

        if ($run instanceof PayrollRun) {
            $this->approver->reject($run);
        }
    }

    private function find(ApprovalDecision $decision): ?PayrollRun
    {
        $found = $this->properties->unrestricted(fn (): ?PayrollRun => PayrollRun::query()->find($decision->payload['payroll_run_id'] ?? 0));

        return $found instanceof PayrollRun ? $found : null;
    }
}
