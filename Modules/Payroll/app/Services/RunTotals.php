<?php

namespace Modules\Payroll\Services;

use Brick\Math\BigDecimal;
use Modules\Payroll\Enums\ComponentKind;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\Payslip;

/**
 * Keeps a payslip's totals in step with its lines, and a run's with its payslips (Step 6.5).
 */
class RunTotals
{
    /** Gross is the earnings, deductions the other deductions; the withheld tax and the employer cost are kept. */
    public function refreshPayslip(Payslip $payslip): Payslip
    {
        $gross = BigDecimal::zero();
        $deductions = BigDecimal::zero();

        foreach ($payslip->lines()->get() as $line) {
            if ($line->kind === ComponentKind::Earning) {
                $gross = $gross->plus((string) $line->amount);
            } elseif ($line->kind === ComponentKind::Deduction && $line->code !== 'TAX') {
                $deductions = $deductions->plus((string) $line->amount);
            }
        }

        $payslip->forceFill(['gross' => (string) $gross->toScale(2), 'deductions' => (string) $deductions->toScale(2), 'net' => (string) $gross->minus($deductions)->minus((string) $payslip->tax)->toScale(2)])->save();

        return $payslip;
    }

    public function refreshRun(PayrollRun $run): PayrollRun
    {
        $slips = $run->payslips()->get();
        $sum = fn (string $column): string => (string) $slips->reduce(fn (BigDecimal $carry, Payslip $slip): BigDecimal => $carry->plus((string) $slip->{$column}), BigDecimal::zero())->toScale(2);
        $run->forceFill([
            'employees_count' => $slips->count(), 'gross_total' => $sum('gross'), 'deductions_total' => $sum('deductions'), 'tax_total' => $sum('tax'), 'net_total' => $sum('net'), 'employer_total' => $sum('employer_cost'),
        ])->save();

        return $run;
    }
}
