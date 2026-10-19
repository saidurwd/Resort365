<?php

namespace Modules\HR\Actions;

use App\Support\Actions\Action;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Models\Employee;
use Modules\HR\Services\EmployeeHistoryWriter;

/**
 * Base of the lifecycle actions (Step 6.1): each changes the employee and writes a history entry in one transaction,
 * and refuses an employee who has left.
 */
abstract class LifecycleAction extends Action
{
    public function __construct(protected readonly EmployeeHistoryWriter $history) {}

    /**
     * @throws HrRuleViolated
     */
    protected function assertCurrent(Employee $employee): void
    {
        if ($employee->isExited()) {
            throw new HrRuleViolated(__(':name has left, so nothing more can be recorded.', ['name' => $employee->full_name]));
        }
    }
}
