<?php

namespace Modules\Payroll\Actions;

use App\Support\Actions\Action;
use Modules\Payroll\Exceptions\PayrollRuleViolated;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\Payslip;
use Modules\Payroll\Models\PayslipLine;

/**
 * Deletes a payroll run that has not gone for approval (Step 6.5), with its payslips.
 */
class DeletePayrollRun extends Action
{
    /**
     * @throws PayrollRuleViolated
     */
    public function handle(PayrollRun $run): void
    {
        if (! $run->isOpen()) {
            throw new PayrollRuleViolated(__('Payroll run :no is :status and cannot be deleted.', ['no' => $run->run_no, 'status' => mb_strtolower($run->status->label())]));
        }

        $this->transaction(function () use ($run): void {
            PayslipLine::query()->whereIn('payslip_id', Payslip::query()->where('payroll_run_id', $run->id)->pluck('id'))->delete();
            Payslip::query()->where('payroll_run_id', $run->id)->delete();
            $run->delete();
        });
    }
}
