<?php

namespace Modules\HR\Actions;

use App\Support\Tenancy\TenantContext;
use Modules\HR\Enums\EmployeeStatus;
use Modules\HR\Enums\ExitReason;
use Modules\HR\Enums\HistoryEvent;
use Modules\HR\Events\EmployeeExited;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Models\Employee;
use Modules\HR\Services\EmployeeHistoryWriter;

/**
 * Records that an employee left (Step 6.1): resignation, termination, end of contract, retirement. The people who
 * reported to them now report to their manager. Fires `EmployeeExited` after commit. The final settlement is payroll's
 * (TODO(step-6.4)).
 */
class ExitEmployee extends LifecycleAction
{
    public function __construct(EmployeeHistoryWriter $history, private readonly TenantContext $tenant)
    {
        parent::__construct($history);
    }

    /**
     * @throws HrRuleViolated
     */
    public function handle(Employee $employee, ExitReason $reason, string $exitedOn, ?string $notes, ?int $userId): Employee
    {
        $this->assertCurrent($employee);

        if ($exitedOn < $employee->joined_on->toDateString()) {
            throw new HrRuleViolated(__('An employee cannot leave before they joined.'));
        }

        return $this->transaction(function () use ($employee, $reason, $exitedOn, $notes, $userId): Employee {
            Employee::query()->where('manager_id', $employee->id)->update(['manager_id' => $employee->manager_id]);
            $employee->forceFill(['status' => EmployeeStatus::Exited, 'exited_on' => $exitedOn, 'exit_reason' => $reason])->save();
            $this->history->record($employee, HistoryEvent::Exit, $exitedOn, ['reason' => $reason->value], $notes, $userId);
            event(new EmployeeExited($this->tenant->tenantOrFail(self::class)->id, $employee->id, $employee->property_id, $exitedOn));

            return $employee;
        });
    }
}
