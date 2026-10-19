<?php

namespace Modules\HR\Actions;

use Modules\HR\Enums\EmployeeStatus;
use Modules\HR\Enums\HistoryEvent;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Models\Employee;

/**
 * Confirms an employee after probation (Step 6.1): the status becomes active and the date is kept.
 */
class ConfirmEmployee extends LifecycleAction
{
    /**
     * @throws HrRuleViolated
     */
    public function handle(Employee $employee, string $effectiveOn, ?string $notes, ?int $userId): Employee
    {
        $this->assertCurrent($employee);

        if ($employee->status !== EmployeeStatus::OnProbation) {
            throw new HrRuleViolated(__(':name is not on probation.', ['name' => $employee->full_name]));
        }

        if ($effectiveOn < $employee->joined_on->toDateString()) {
            throw new HrRuleViolated(__('The confirmation cannot be before the employee joined.'));
        }

        return $this->transaction(function () use ($employee, $effectiveOn, $notes, $userId): Employee {
            $employee->forceFill(['status' => EmployeeStatus::Active, 'confirmed_on' => $effectiveOn])->save();
            $this->history->record($employee, HistoryEvent::Confirmation, $effectiveOn, ['probation_ends_on' => $employee->probation_ends_on?->toDateString()], $notes, $userId);

            return $employee;
        });
    }
}
