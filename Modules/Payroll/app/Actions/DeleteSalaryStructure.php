<?php

namespace Modules\Payroll\Actions;

use App\Support\Actions\Action;
use Modules\Payroll\Exceptions\PayrollRuleViolated;
use Modules\Payroll\Models\EmployeeSalary;
use Modules\Payroll\Models\SalaryStructure;

/**
 * Deletes a salary structure nobody has been paid by (Step 6.4); otherwise it can only be made inactive.
 */
class DeleteSalaryStructure extends Action
{
    /**
     * @throws PayrollRuleViolated
     */
    public function handle(SalaryStructure $structure): void
    {
        if (EmployeeSalary::query()->where('salary_structure_id', $structure->id)->exists()) {
            throw new PayrollRuleViolated(__('":name" is assigned to employees, so it can only be made inactive.', ['name' => $structure->name]));
        }

        $this->transaction(function () use ($structure): void {
            $structure->lines()->delete();
            $structure->delete();
        });
    }
}
