<?php

namespace Modules\HR\Actions;

use App\Support\Tenancy\PropertyAccessDenied;
use App\Support\Tenancy\TenantContext;
use Modules\HR\Enums\HistoryEvent;
use Modules\HR\Events\EmployeeTransferred;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Models\Employee;
use Modules\HR\Services\EmployeeHistoryWriter;
use Modules\Property\Contracts\DepartmentDirectory;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Property\DTOs\DepartmentSummary;
use Modules\Property\DTOs\PropertySummary;

/**
 * Moves an employee to another property, and to another department if asked (Step 6.1). The person doing it needs
 * access to the new property. Fires `EmployeeTransferred` after commit.
 */
class TransferEmployee extends LifecycleAction
{
    public function __construct(
        EmployeeHistoryWriter $history,
        private readonly PropertyDirectory $properties,
        private readonly DepartmentDirectory $departments,
        private readonly TenantContext $tenant,
    ) {
        parent::__construct($history);
    }

    /**
     * @throws HrRuleViolated
     */
    public function handle(Employee $employee, int $propertyId, ?int $departmentId, string $effectiveOn, ?string $notes, ?int $userId): Employee
    {
        $this->assertCurrent($employee);
        $target = $this->properties->find($propertyId);

        if (! $target instanceof PropertySummary) {
            throw new HrRuleViolated(__('Choose a property you can work in.'));
        }

        $department = $departmentId !== null ? $this->departments->find($departmentId) : null;

        if ($departmentId !== null && (! $department instanceof DepartmentSummary || ! $department->isActive)) {
            throw new HrRuleViolated(__('Choose an active department.'));
        }

        if ($propertyId === $employee->property_id && ($departmentId === null || $departmentId === $employee->department_id)) {
            throw new HrRuleViolated(__(':name already works there.', ['name' => $employee->full_name]));
        }

        return $this->transaction(function () use ($employee, $target, $department, $effectiveOn, $notes, $userId): Employee {
            $fromProperty = $employee->property_id;
            $from = $this->properties->find($fromProperty)->name ?? '—';
            $details = ['property' => [$from, $target->name]];

            if ($department instanceof DepartmentSummary && $department->id !== $employee->department_id) {
                $details['department'] = [$this->departments->find($employee->department_id)->name ?? '—', $department->name];
            }

            try {
                $employee->forceFill(['property_id' => $target->id, 'department_id' => $department->id ?? $employee->department_id])->save();
            } catch (PropertyAccessDenied) {
                throw new HrRuleViolated(__('You do not have access to :property.', ['property' => $target->name]));
            }

            $this->history->record($employee, HistoryEvent::Transfer, $effectiveOn, $details, $notes, $userId);

            if ($fromProperty !== $target->id) {
                event(new EmployeeTransferred($this->tenant->tenantOrFail(self::class)->id, $employee->id, $fromProperty, $target->id, $effectiveOn));
            }

            return $employee;
        });
    }
}
