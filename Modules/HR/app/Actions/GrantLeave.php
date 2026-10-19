<?php

namespace Modules\HR\Actions;

use App\Support\Actions\Action;
use Carbon\CarbonImmutable;
use Modules\HR\Enums\EmployeeStatus;
use Modules\HR\Models\Employee;
use Modules\HR\Models\LeaveBalance;
use Modules\HR\Models\LeavePolicy;
use Modules\HR\Services\LeaveBalanceMath;

/**
 * Grants a year of leave to employees (Step 6.3), safe to repeat: for each active leave type with a balance and each
 * employee it applies to (employment type, gender, months of service), the balance is created or brought up to what the
 * policy says is earned as of the date (all of it, or a twelfth a month), and unused days from the year before carry
 * forward up to the policy's limit. Used, pending and HR's adjustments are never touched. The scheduler runs it daily.
 */
class GrantLeave extends Action
{
    /**
     * @return int the balances created or changed
     */
    public function handle(int $year, CarbonImmutable $asOf, ?int $employeeId = null): int
    {
        $policies = LeavePolicy::query()->with('leaveType')->get()->filter(fn (LeavePolicy $policy): bool => $policy->leaveType->is_active && $policy->leaveType->has_balance);
        $employees = Employee::query()->when($employeeId !== null, fn ($query) => $query->whereKey($employeeId))
            ->where(fn ($query) => $query->where('status', '!=', EmployeeStatus::Exited->value)->orWhereYear('exited_on', '>=', $year))->get();
        $touched = 0;

        return $this->transaction(function () use ($policies, $employees, $year, $asOf, &$touched): int {
            foreach ($employees as $employee) {
                foreach ($policies as $policy) {
                    $type = $policy->leaveType;

                    if (($type->gender !== null && $employee->gender !== $type->gender) || ($policy->employment_types !== null && ! in_array($employee->employment_type->value, $policy->employment_types, true))) {
                        continue;
                    }

                    $entitled = LeaveBalanceMath::entitlement((string) $policy->annual_days, $policy->accrual, CarbonImmutable::parse($employee->joined_on->toDateString()), $policy->min_service_months, $asOf, $year,
                        $employee->exited_on !== null ? CarbonImmutable::parse($employee->exited_on->toDateString()) : null);

                    if ($entitled === null) {
                        continue;
                    }

                    $previous = LeaveBalance::query()->where(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => $year - 1])->first();
                    $carried = $previous instanceof LeaveBalance ? LeaveBalanceMath::carryForward($previous->available(), (string) $policy->carry_forward_max) : '0.0';
                    $balance = LeaveBalance::query()->firstOrNew(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => $year]);

                    if ($balance->exists && (string) $balance->entitled === $entitled && (string) $balance->carried_forward === $carried) {
                        continue;
                    }

                    $balance->forceFill(['entitled' => $entitled, 'carried_forward' => $carried])->save();
                    $touched++;
                }
            }

            return $touched;
        });
    }
}
