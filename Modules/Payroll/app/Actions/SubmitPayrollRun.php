<?php

namespace Modules\Payroll\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Modules\Core\Contracts\ApprovalEngine;
use Modules\Core\DTOs\ApprovalSummary;
use Modules\Payroll\Enums\RunStatus;
use Modules\Payroll\Exceptions\PayrollRuleViolated;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\Payslip;
use Modules\Payroll\Services\PayrollApprover;

/**
 * Sends a calculated payroll run for approval (Step 6.5): the `payroll_run` workflow, with the net total as the amount. From
 * here the run is locked. A run below every step of the workflow is approved at once. Refused while a payslip has a negative net
 * pay, or there is no payslip.
 */
class SubmitPayrollRun extends Action
{
    public function __construct(
        private readonly ApprovalEngine $approvals,
        private readonly PayrollApprover $approver,
    ) {}

    /**
     * @throws PayrollRuleViolated
     */
    public function handle(PayrollRun $run, int $userId): PayrollRun
    {
        return $this->transaction(function () use ($run, $userId): PayrollRun {
            $locked = PayrollRun::query()->lockForUpdate()->findOrFail($run->id);

            if ($locked->status !== RunStatus::Calculated) {
                throw new PayrollRuleViolated(__('Payroll run :no is :status; only a calculated run goes for approval.', ['no' => $locked->run_no, 'status' => mb_strtolower($locked->status->label())]));
            }

            $slips = $locked->payslips()->get();

            if ($slips->isEmpty()) {
                throw new PayrollRuleViolated(__('There are no payslips in this run.'));
            }

            $negative = $slips->filter(fn (Payslip $slip): bool => BigDecimal::of((string) $slip->net)->isNegative());

            if ($negative->isNotEmpty()) {
                throw new PayrollRuleViolated(__('These employees have a negative net pay; adjust their payslips first: :names.', ['names' => $negative->pluck('employee_name')->implode(', ')]));
            }

            $approval = $this->approvals->submit('payroll_run', (string) $locked->net_total, __('Payroll run :no', ['no' => $locked->run_no]), $userId, ['payroll_run_id' => $locked->id], $locked->property_id, 'payroll_run', $locked->id);

            if ($approval instanceof ApprovalSummary) {
                $locked->forceFill(['status' => RunStatus::Pending, 'approval_request_id' => $approval->id])->save();
            } else {
                $this->approver->approve($locked, $userId);
            }

            return $locked->refresh();
        }, attempts: 3);
    }
}
