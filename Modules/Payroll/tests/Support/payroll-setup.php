<?php

use Modules\HR\Models\Employee;
use Modules\Payroll\Actions\SeedBangladeshPayroll;
use Modules\Payroll\Actions\SetEmployeeSalary;
use Modules\Payroll\Models\EmployeeSalary;
use Modules\Payroll\Models\SalaryStructure;

require_once __DIR__.'/../../../HR/tests/Support/hr-setup.php';
require_once __DIR__.'/payroll-fixtures.php';

/** Loads the Bangladesh starter pack into the test tenant. */
function pyPrepare(): void
{
    booking(fn (): array => app(SeedBangladeshPayroll::class)->handle());
}

function pyStructure(string $code = 'STD'): SalaryStructure
{
    return booking(fn (): SalaryStructure => SalaryStructure::query()->where('code', $code)->firstOrFail());
}

/**
 * Gives the employee a salary from a date on the starter structure.
 *
 * @param  array<string, mixed>  $overrides
 */
function pySalary(Employee $employee, string $basic = '25000', string $from = '2025-08-01', array $overrides = []): EmployeeSalary
{
    return booking(fn (): EmployeeSalary => app(SetEmployeeSalary::class)->handle($employee->id, [
        'salary_structure_id' => pyStructure()->id, 'effective_from' => $from, 'basic' => $basic, 'tax_category' => 'general', 'tax_area' => 'metro', ...$overrides,
    ], 1));
}
