<?php

namespace Modules\Payroll\Actions;

use App\Support\Actions\Action;
use Modules\Payroll\Exceptions\PayrollRuleViolated;
use Modules\Payroll\Models\EmployeeSalary;

/**
 * Removes a salary that has not started yet (Step 6.4), e.g. a raise entered with the wrong date; the salary before it is
 * extended over the gap. A salary that has started is history and stays.
 */
class DeleteEmployeeSalary extends Action
{
    /**
     * @throws PayrollRuleViolated
     */
    public function handle(EmployeeSalary $salary, ?string $today = null): void
    {
        $today ??= now()->toDateString();

        if ($salary->effective_from->toDateString() <= $today) {
            throw new PayrollRuleViolated(__('This salary has started, so it stays as history. Add a new salary from the date of the change.'));
        }

        $this->transaction(function () use ($salary): void {
            EmployeeSalary::query()->where('employee_id', $salary->employee_id)->whereDate('effective_to', $salary->effective_from->subDay()->toDateString())
                ->update(['effective_to' => $salary->effective_to?->toDateString()]);
            $salary->delete();
        });
    }
}
