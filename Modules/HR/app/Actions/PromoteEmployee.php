<?php

namespace Modules\HR\Actions;

use Modules\HR\Enums\HistoryEvent;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Models\Designation;
use Modules\HR\Models\Employee;
use Modules\HR\Models\Grade;

/**
 * Gives an employee a new designation and/or grade (Step 6.1); the history keeps the old and new names.
 */
class PromoteEmployee extends LifecycleAction
{
    /**
     * @throws HrRuleViolated
     */
    public function handle(Employee $employee, int $designationId, ?int $gradeId, string $effectiveOn, ?string $notes, ?int $userId): Employee
    {
        $this->assertCurrent($employee);
        $designation = Designation::query()->find($designationId);
        $grade = $gradeId !== null ? Grade::query()->find($gradeId) : null;

        if (! $designation instanceof Designation || ! $designation->is_active) {
            throw new HrRuleViolated(__('Choose an active designation.'));
        }

        if ($gradeId !== null && (! $grade instanceof Grade || ! $grade->is_active)) {
            throw new HrRuleViolated(__('Choose an active grade.'));
        }

        if ($designation->id === $employee->designation_id && $gradeId === $employee->grade_id) {
            throw new HrRuleViolated(__('That is the designation and grade :name already has.', ['name' => $employee->full_name]));
        }

        return $this->transaction(function () use ($employee, $designation, $grade, $effectiveOn, $notes, $userId): Employee {
            $details = ['designation' => [$employee->designation->name, $designation->name], 'grade' => [$employee->grade->name ?? null, $grade->name ?? null]];
            $employee->forceFill(['designation_id' => $designation->id, 'grade_id' => $grade?->id])->save();
            $this->history->record($employee, HistoryEvent::Promotion, $effectiveOn, $details, $notes, $userId);

            return $employee;
        });
    }
}
