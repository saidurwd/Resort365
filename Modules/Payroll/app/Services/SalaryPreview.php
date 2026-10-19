<?php

namespace Modules\Payroll\Services;

use Modules\Payroll\DTOs\SalaryBreakdown;
use Modules\Payroll\Models\EmployeeSalary;
use Modules\Payroll\Models\SalaryStructure;
use Modules\Payroll\Models\TaxTable;
use Modules\Payroll\Services\Calculation\SalaryCalculator;

/**
 * An employee's month of pay on a date, from their salary record, structure and the tax table in force (Step 6.4).
 */
class SalaryPreview
{
    public function __construct(
        private readonly SalaryCalculator $calculator,
        private readonly StructureLoader $structures,
        private readonly TaxTableResolver $taxTables,
    ) {}

    /** The salary record in force on a date. */
    public function salaryOn(int $employeeId, string $date): ?EmployeeSalary
    {
        return EmployeeSalary::query()->with('structure')->where('employee_id', $employeeId)->whereDate('effective_from', '<=', $date)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))->orderByDesc('effective_from')->first();
    }

    public function forEmployee(int $employeeId, string $date): ?SalaryBreakdown
    {
        $salary = $this->salaryOn($employeeId, $date);

        return $salary instanceof EmployeeSalary ? $this->forSalary($salary, $date) : null;
    }

    public function forSalary(EmployeeSalary $salary, string $date): SalaryBreakdown
    {
        return $this->forStructure($salary->structure, (string) $salary->basic, $salary->tax_category, $salary->tax_area, $date, $salary->amounts ?? []);
    }

    /**
     * @param  array<string, string>  $amounts
     */
    public function forStructure(SalaryStructure $structure, string $basic, string $taxCategory, string $taxArea, string $date, array $amounts = []): SalaryBreakdown
    {
        $table = $this->taxTables->forDate($date);

        return $this->calculator->calculate($this->structures->lines($structure), $basic, $table instanceof TaxTable ? $this->taxTables->data($table) : null, $taxCategory, $taxArea, $amounts);
    }
}
