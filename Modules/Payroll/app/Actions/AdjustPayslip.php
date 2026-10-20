<?php

namespace Modules\Payroll\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Payroll\Enums\ComponentKind;
use Modules\Payroll\Enums\LineSource;
use Modules\Payroll\Exceptions\PayrollRuleViolated;
use Modules\Payroll\Models\Payslip;
use Modules\Payroll\Models\PayslipLine;
use Modules\Payroll\Services\RunTotals;

/**
 * Adds a one-off earning or deduction to a payslip, with a reason (Step 6.5), while the run is still open: a bonus, a fine, a
 * correction. The net pay follows; the withheld income tax does not change.
 */
class AdjustPayslip extends Action
{
    public function __construct(private readonly RunTotals $totals) {}

    /**
     * @throws PayrollRuleViolated
     */
    public function handle(Payslip $payslip, ComponentKind $kind, string $name, string $amount, ?string $note): PayslipLine
    {
        Payslip::assertRunOpen($payslip->payroll_run_id);

        if ($kind === ComponentKind::Employer) {
            throw new PayrollRuleViolated(__('An adjustment is an earning or a deduction.'));
        }

        if (! is_numeric($amount) || ! BigDecimal::of($amount)->isPositive()) {
            throw new PayrollRuleViolated(__('Enter an amount more than zero.'));
        }

        if (trim($name) === '' || ($note === null || trim($note) === '')) {
            throw new PayrollRuleViolated(__('Give the adjustment a name and say why.'));
        }

        return $this->transaction(function () use ($payslip, $kind, $name, $amount, $note): PayslipLine {
            $line = PayslipLine::query()->create([
                'payslip_id' => $payslip->id, 'code' => 'ADJ', 'name' => trim($name), 'kind' => $kind, 'is_taxable' => false, 'amount' => (string) BigDecimal::of($amount)->toScale(2, RoundingMode::HalfUp),
                'source' => LineSource::Adjustment, 'note' => trim($note), 'sort_order' => 900 + $payslip->lines()->count(),
            ]);
            $this->totals->refreshPayslip($payslip);
            $this->totals->refreshRun($payslip->run);

            return $line;
        });
    }
}
