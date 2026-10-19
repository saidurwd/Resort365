<?php

namespace Modules\Payroll\Services\Calculation;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Payroll\DTOs\TaxResult;
use Modules\Payroll\DTOs\TaxTableData;
use Modules\Payroll\Exceptions\PayrollRuleViolated;

/**
 * Income tax on a year of salary, from a tax table (Step 6.4). Pure.
 *
 * - The exemption is a fraction of the salary income up to a cap (in Bangladesh one-third up to 450,000).
 * - The taxable income above the taxpayer category's tax-free amount is taxed band by band.
 * - Once tax is due it is at least the minimum for the taxpayer's area.
 * - Tax is withheld in twelve equal parts.
 */
final class IncomeTaxCalculator
{
    /**
     * @throws PayrollRuleViolated
     */
    public function calculate(string $annualIncome, TaxTableData $table, string $category, string $area): TaxResult
    {
        $income = BigDecimal::of($annualIncome)->toScale(2, RoundingMode::HalfUp);
        $threshold = isset($table->thresholds[$category]) ? BigDecimal::of($table->thresholds[$category]) : throw new PayrollRuleViolated(__('The tax table has no tax-free amount for ":category".', ['category' => $category]));
        $exemption = $income->multipliedBy($this->fraction($table->exemptionFraction))->toScale(2, RoundingMode::HalfUp);
        $cap = BigDecimal::of($table->exemptionCap);
        $exemption = $exemption->isGreaterThan($cap) ? $cap->toScale(2) : $exemption;
        $taxable = $income->minus($exemption);
        $taxable = $taxable->isNegative() ? BigDecimal::zero()->toScale(2) : $taxable;
        $tax = BigDecimal::zero();
        $minimum = false;

        if ($taxable->isGreaterThan($threshold)) {
            $remaining = $taxable->minus($threshold);

            foreach ($table->slabs as $slab) {
                $portion = $slab['width'] === null || $remaining->isLessThanOrEqualTo($slab['width']) ? $remaining : BigDecimal::of($slab['width']);
                $tax = $tax->plus($portion->multipliedBy($slab['rate'])->dividedBy(100, 10, RoundingMode::HalfUp));
                $remaining = $remaining->minus($portion);

                if ($remaining->isZero()) {
                    break;
                }
            }

            $tax = $tax->toScale(2, RoundingMode::HalfUp);
            $floor = isset($table->minimumTax[$area]) ? BigDecimal::of($table->minimumTax[$area]) : BigDecimal::zero();

            if ($tax->isLessThan($floor)) {
                $tax = $floor->toScale(2);
                $minimum = true;
            }
        }

        return new TaxResult((string) $income, (string) $exemption, (string) $taxable, (string) $threshold->toScale(2), (string) $tax->toScale(2), $minimum, (string) $tax->dividedBy(12, 2, RoundingMode::HalfUp));
    }

    /** A fraction such as "1/3" or a decimal such as "0.25". */
    private function fraction(string $value): BigDecimal
    {
        if (str_contains($value, '/')) {
            [$top, $bottom] = array_map(trim(...), explode('/', $value, 2));

            return BigDecimal::of($top)->dividedBy($bottom, 10, RoundingMode::HalfUp);
        }

        return BigDecimal::of($value);
    }
}
