<?php

namespace Modules\HR\Services;

use Modules\HR\Contracts\EmployeeDirectory;
use Modules\HR\DTOs\EmployeeSummary;
use Modules\HR\Enums\EmployeeStatus;
use Modules\HR\Models\Employee;

/**
 * HR's implementation of `EmployeeDirectory` (Step 6.1).
 */
class EmployeeDirectoryService implements EmployeeDirectory
{
    public function find(int $id): ?EmployeeSummary
    {
        $employee = Employee::query()->with('designation')->find($id);

        return $employee instanceof Employee ? $this->summary($employee) : null;
    }

    public function forUser(int $userId): ?EmployeeSummary
    {
        $employee = Employee::query()->with('designation')->where('user_id', $userId)->first();

        return $employee instanceof Employee ? $this->summary($employee) : null;
    }

    public function search(string $term, int $limit = 15, bool $activeOnly = true): array
    {
        $term = trim($term);

        return Employee::query()->with('designation')
            ->when($activeOnly, fn ($query) => $query->where('status', '!=', EmployeeStatus::Exited->value))
            ->when($term !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('employee_no', 'like', $term.'%')->orWhere('first_name', 'like', $term.'%')
                ->orWhere('last_name', 'like', $term.'%')->orWhereRaw("CONCAT(first_name, ' ', COALESCE(last_name, '')) like ?", [$term.'%'])))
            ->orderBy('first_name')->orderBy('last_name')->limit($limit)->get()
            ->map(fn (Employee $employee): EmployeeSummary => $this->summary($employee))->values()->all();
    }

    public function forProperty(int $propertyId, ?int $departmentId = null, bool $activeOnly = true): array
    {
        return Employee::query()->with('designation')->where('property_id', $propertyId)
            ->when($departmentId !== null, fn ($query) => $query->where('department_id', $departmentId))
            ->when($activeOnly, fn ($query) => $query->where('status', '!=', EmployeeStatus::Exited->value))
            ->orderBy('first_name')->orderBy('last_name')->get()
            ->map(fn (Employee $employee): EmployeeSummary => $this->summary($employee))->values()->all();
    }

    private function summary(Employee $employee): EmployeeSummary
    {
        return new EmployeeSummary($employee->id, $employee->employee_no, $employee->full_name, $employee->property_id, $employee->department_id, $employee->designation->name, $employee->status, $employee->manager_id, $employee->user_id);
    }
}
