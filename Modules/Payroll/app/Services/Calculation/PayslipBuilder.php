<?php

namespace Modules\Payroll\Services\Calculation;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Payroll\DTOs\PayslipData;
use Modules\Payroll\DTOs\PayslipInput;
use Modules\Payroll\DTOs\PayslipLineData;
use Modules\Payroll\DTOs\StructureLine;
use Modules\Payroll\Enums\CalcType;
use Modules\Payroll\Enums\ComponentFrequency;
use Modules\Payroll\Enums\ComponentKind;
use Modules\Payroll\Enums\LineSource;
use Modules\Payroll\Exceptions\PayrollRuleViolated;

/**
 * Works out one employee's month for a payroll run (Step 6.5). Pure.
 *
 * Starts from the salary structure (`SalaryCalculator`), adding what the month brought:
 * - overtime: hours × (basic ÷ hours a month) × the multiplier. It is taxable, so the withheld tax follows it;
 * - the month's share of the service charge and tips pools (Step 6.6), as taxable earnings like overtime;
 * - the loan and advance installments due, as deductions;
 * - the annual components chosen for this run (festival bonuses), paid once in full. The yearly tax already counts them,
 *   so paying one adds no tax;
 * - a part month (joiner or leaver): every regular line and the tax are scaled by the share of the month worked;
 * - absence: absent days plus days of unpaid leave, each costing the regular gross ÷ the days basis.
 */
final readonly class PayslipBuilder
{
    private const array VARIABLE = ['OT', 'SCSHARE', 'TIPSHARE', 'LOAN', 'ADVANCE', 'ABSENCE'];

    public function __construct(private SalaryCalculator $calculator = new SalaryCalculator) {}

    /**
     * @throws PayrollRuleViolated
     */
    public function build(PayslipInput $input): PayslipData
    {
        $has = array_map(fn (StructureLine $line): string => strtoupper($line->code), $input->lines);
        $lines = $input->lines;
        $overtime = $this->overtime($input);

        foreach ([['OT', 'Overtime', ComponentKind::Earning, true, $overtime], ['SCSHARE', 'Service charge share', ComponentKind::Earning, true, $input->serviceShare], ['TIPSHARE', 'Tips share', ComponentKind::Earning, true, $input->tipsShare], ['LOAN', 'Loan instalment', ComponentKind::Deduction, false, $input->loanDue], ['ADVANCE', 'Salary advance', ComponentKind::Deduction, false, $input->advanceDue]] as [$code, $name, $kind, $taxable, $amount]) {
            if (BigDecimal::of($amount)->isPositive() && ! in_array($code, $has, true)) {
                $lines[] = new StructureLine($code, $name, $kind, $taxable, ComponentFrequency::Monthly, 1, CalcType::Fixed, $amount);
            }
        }

        $worked = $this->calculator->calculate($lines, $input->basic, $input->tax, $input->taxCategory, $input->taxArea, $input->employeeAmounts);
        $fraction = BigDecimal::of($input->fraction);
        $part = $fraction->isLessThan(1);
        $out = [];
        $gross = BigDecimal::zero();
        $regular = BigDecimal::zero();
        $deductions = BigDecimal::zero();
        $employer = BigDecimal::zero();
        $order = 0;
        $tax = BigDecimal::of($worked->tax);

        foreach ($worked->lines as $line) {
            $code = strtoupper($line->code);
            $order += 10;

            if ($code === 'TAX') {
                continue;
            }

            $amount = BigDecimal::of($line->amount);

            if ($line->frequency === ComponentFrequency::Annual) {
                if (! in_array($code, array_map(strtoupper(...), $input->includeAnnual), true)) {
                    continue;
                }

                $out[] = new PayslipLineData($line->code, $line->name, $line->kind, $line->taxable, (string) $amount, LineSource::Structure, $order);
                $gross = $gross->plus($amount);

                continue;
            }

            $variable = in_array($code, self::VARIABLE, true);

            if ($part && ! $variable) {
                $amount = $amount->multipliedBy($fraction)->toScale(2, RoundingMode::HalfUp);
            }

            $out[] = new PayslipLineData($line->code, $line->name, $line->kind, $line->taxable, (string) $amount->toScale(2), $variable ? LineSource::Variable : LineSource::Structure, $order);

            if ($line->kind === ComponentKind::Earning) {
                $gross = $gross->plus($amount);
                $regular = $variable ? $regular : $regular->plus($amount);
            } elseif ($line->kind === ComponentKind::Deduction) {
                $deductions = $deductions->plus($amount);
            } else {
                $employer = $employer->plus($amount);
            }
        }

        if ($part) {
            $tax = $tax->multipliedBy($fraction)->toScale(2, RoundingMode::HalfUp);
        }

        $absence = $this->absence($input, $regular);

        if ($absence->isPositive()) {
            $out[] = new PayslipLineData('ABSENCE', 'Absence deduction', ComponentKind::Deduction, false, (string) $absence, LineSource::Variable, $order + 10);
            $deductions = $deductions->plus($absence);
        }

        return new PayslipData(
            $out, (string) $gross->toScale(2), (string) $deductions->toScale(2), (string) $tax->toScale(2), (string) $gross->minus($deductions)->minus($tax)->toScale(2, RoundingMode::HalfUp), (string) $employer->toScale(2),
        );
    }

    private function overtime(PayslipInput $input): string
    {
        if ($input->overtimeMinutes <= 0) {
            return '0.00';
        }

        $hourly = BigDecimal::of($input->basic)->dividedBy($input->rules->overtimeHoursPerMonth, 10, RoundingMode::HalfUp);

        return (string) BigDecimal::of($input->overtimeMinutes)->dividedBy(60, 10, RoundingMode::HalfUp)->multipliedBy($hourly)->multipliedBy($input->rules->overtimeMultiplier)->toScale(2, RoundingMode::HalfUp);
    }

    /** Days lost × the regular gross ÷ the days basis, never more than the regular gross. */
    private function absence(PayslipInput $input, BigDecimal $regular): BigDecimal
    {
        $days = BigDecimal::of($input->absentDays)->plus($input->unpaidLeaveDays);

        if (! $days->isPositive() || ! $regular->isPositive()) {
            return BigDecimal::zero()->toScale(2);
        }

        $amount = $regular->dividedBy($input->rules->salaryDaysBasis, 10, RoundingMode::HalfUp)->multipliedBy($days)->toScale(2, RoundingMode::HalfUp);

        return $amount->isGreaterThan($regular) ? $regular->toScale(2) : $amount;
    }
}
