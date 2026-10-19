<?php

namespace Modules\Payroll\Services\Calculation;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Payroll\DTOs\BreakdownLine;
use Modules\Payroll\DTOs\SalaryBreakdown;
use Modules\Payroll\DTOs\StructureLine;
use Modules\Payroll\DTOs\TaxResult;
use Modules\Payroll\DTOs\TaxTableData;
use Modules\Payroll\Enums\CalcType;
use Modules\Payroll\Enums\ComponentFrequency;
use Modules\Payroll\Enums\ComponentKind;
use Modules\Payroll\Exceptions\PayrollRuleViolated;

/**
 * Works out a month of an employee's pay from a salary structure (Step 6.4). Pure.
 *
 * Each component is a fixed amount, a percentage of another component, a formula, or an amount set for the employee,
 * rounded half-up to the cent. `BASIC` is the employee's basic salary whatever its line says; `TAX` is withheld by the income
 * tax rules from the year's taxable salary: twelve months of the taxable monthly earnings plus the taxable annual components
 * (the festival bonuses). Gross is the monthly earnings; net is gross less the deductions and the tax. Employer costs
 * (employer provident fund, gratuity provision) are shown apart and do not reduce net pay.
 */
final readonly class SalaryCalculator
{
    public function __construct(
        private FormulaEvaluator $formulas = new FormulaEvaluator,
        private IncomeTaxCalculator $taxes = new IncomeTaxCalculator,
    ) {}

    /**
     * @param  list<StructureLine>  $lines
     * @param  array<string, string>  $employeeAmounts  component code => amount, for lines set per employee
     *
     * @throws PayrollRuleViolated
     */
    public function calculate(array $lines, string $basic, ?TaxTableData $tax, string $taxCategory = 'general', string $taxArea = 'other', array $employeeAmounts = []): SalaryBreakdown
    {
        $values = ['BASIC' => BigDecimal::of($basic)->toScale(2, RoundingMode::HalfUp)];
        $amounts = $this->resolve($lines, $values, array_change_key_case($employeeAmounts, CASE_UPPER));
        $earnings = BigDecimal::zero();
        $taxableMonthly = BigDecimal::zero();
        $annual = BigDecimal::zero();
        $deductions = BigDecimal::zero();
        $employer = BigDecimal::zero();
        $taxLine = null;

        foreach ($lines as $line) {
            $code = strtoupper($line->code);

            if ($code === 'TAX') {
                $taxLine = $line;

                continue;
            }

            $amount = $amounts[$code];
            $annualLine = $line->frequency === ComponentFrequency::Annual;

            if ($line->kind === ComponentKind::Earning) {
                if ($annualLine) {
                    $annual = $annual->plus($line->taxable ? $amount->multipliedBy($line->annualTimes) : BigDecimal::zero());
                } else {
                    $earnings = $earnings->plus($amount);
                    $taxableMonthly = $taxableMonthly->plus($line->taxable ? $amount : BigDecimal::zero());
                }
            } elseif (! $annualLine) {
                $line->kind === ComponentKind::Deduction ? $deductions = $deductions->plus($amount) : $employer = $employer->plus($amount);
            }
        }

        $annualIncome = $taxableMonthly->multipliedBy(12)->plus($annual)->toScale(2, RoundingMode::HalfUp);
        $result = $taxLine instanceof StructureLine && $tax instanceof TaxTableData ? $this->taxes->calculate((string) $annualIncome, $tax, $taxCategory, $taxArea) : null;
        $withheld = $result instanceof TaxResult ? BigDecimal::of($result->monthly) : BigDecimal::zero()->toScale(2);

        $out = [];

        foreach ($lines as $line) {
            $code = strtoupper($line->code);
            $amount = $code === 'TAX' ? $withheld : $amounts[$code];
            $out[] = new BreakdownLine($line->code, $line->name, $line->kind, $line->taxable, $line->frequency, $line->annualTimes, (string) $amount->toScale(2, RoundingMode::HalfUp));
        }

        return new SalaryBreakdown(
            $out, (string) $earnings->toScale(2), (string) $deductions->toScale(2), (string) $withheld->toScale(2), (string) $earnings->minus($deductions)->minus($withheld)->toScale(2, RoundingMode::HalfUp),
            (string) $employer->toScale(2), (string) $annualIncome, $result,
        );
    }

    /**
     * Each line's amount, resolving a component after the ones it depends on.
     *
     * @param  list<StructureLine>  $lines
     * @param  array<string, BigDecimal>  $values
     * @param  array<string, string>  $employeeAmounts
     * @return array<string, BigDecimal>
     *
     * @throws PayrollRuleViolated
     */
    private function resolve(array $lines, array $values, array $employeeAmounts): array
    {
        $pending = [];

        foreach ($lines as $line) {
            $code = strtoupper($line->code);

            if ($code === 'TAX') {
                continue;
            }

            if (isset($pending[$code])) {
                throw new PayrollRuleViolated(__('":code" is in the structure twice.', ['code' => $code]));
            }

            $pending[$code] = $line;
        }

        $amounts = [];

        while ($pending !== []) {
            $progress = false;

            foreach ($pending as $code => $line) {
                $needs = $this->needs($line);

                if (array_diff($needs, array_keys($values)) !== []) {
                    continue;
                }

                $amount = $code === 'BASIC' ? $values['BASIC'] : $this->amount($line, $values, $employeeAmounts[$code] ?? null);
                $values[$code] = $amount;
                $amounts[$code] = $amount;
                unset($pending[$code]);
                $progress = true;
            }

            if (! $progress) {
                $missing = array_unique(array_merge(...array_values(array_map(fn (StructureLine $line): array => array_diff($this->needs($line), array_keys($values), array_map(strtoupper(...), array_column($lines, 'code'))), $pending))));

                throw new PayrollRuleViolated($missing !== []
                    ? __('A line uses :codes, which the structure does not have.', ['codes' => implode(', ', $missing)])
                    : __('The structure refers to itself in a circle: :codes.', ['codes' => implode(', ', array_keys($pending))]));
            }
        }

        return $amounts;
    }

    /**
     * @return list<string>
     *
     * @throws PayrollRuleViolated
     */
    private function needs(StructureLine $line): array
    {
        return match ($line->calcType) {
            CalcType::Percent => [strtoupper((string) $line->percentOf)],
            CalcType::Formula => $this->formulas->references((string) $line->formula),
            default => [],
        };
    }

    /**
     * @param  array<string, BigDecimal>  $values
     */
    private function amount(StructureLine $line, array $values, ?string $set): BigDecimal
    {
        $amount = match ($line->calcType) {
            CalcType::Fixed => BigDecimal::of($line->amount ?? '0'),
            CalcType::Employee => BigDecimal::of($set ?? $line->amount ?? '0'),
            CalcType::Percent => $values[strtoupper((string) $line->percentOf)]->multipliedBy($line->percent ?? '0')->dividedBy(100, 10, RoundingMode::HalfUp),
            CalcType::Formula => BigDecimal::of($this->formulas->evaluate((string) $line->formula, array_map(fn (BigDecimal $value): string => (string) $value, $values))),
        };

        return $amount->toScale(2, RoundingMode::HalfUp);
    }
}
