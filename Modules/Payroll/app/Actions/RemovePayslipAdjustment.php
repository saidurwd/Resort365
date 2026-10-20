<?php

namespace Modules\Payroll\Actions;

use App\Support\Actions\Action;
use Modules\Payroll\Enums\LineSource;
use Modules\Payroll\Exceptions\PayrollRuleViolated;
use Modules\Payroll\Models\Payslip;
use Modules\Payroll\Models\PayslipLine;
use Modules\Payroll\Services\RunTotals;

/**
 * Takes an adjustment off a payslip while the run is open (Step 6.5); other lines come from the calculation and are not removed.
 */
class RemovePayslipAdjustment extends Action
{
    public function __construct(private readonly RunTotals $totals) {}

    /**
     * @throws PayrollRuleViolated
     */
    public function handle(PayslipLine $line): void
    {
        $payslip = Payslip::query()->findOrFail($line->payslip_id);
        Payslip::assertRunOpen($payslip->payroll_run_id);

        if ($line->source !== LineSource::Adjustment) {
            throw new PayrollRuleViolated(__('Only an adjustment can be removed; calculate the run again to change the rest.'));
        }

        $this->transaction(function () use ($line, $payslip): void {
            $line->delete();
            $this->totals->refreshPayslip($payslip);
            $this->totals->refreshRun($payslip->run);
        });
    }
}
