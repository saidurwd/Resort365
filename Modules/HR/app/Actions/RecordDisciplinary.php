<?php

namespace Modules\HR\Actions;

use Modules\HR\Enums\DisciplinaryAction;
use Modules\HR\Enums\HistoryEvent;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Models\Employee;

/**
 * Records a disciplinary action in the employee's history (Step 6.1); what happened is required.
 */
class RecordDisciplinary extends LifecycleAction
{
    /**
     * @throws HrRuleViolated
     */
    public function handle(Employee $employee, DisciplinaryAction $action, string $effectiveOn, string $notes, ?int $userId): Employee
    {
        $this->assertCurrent($employee);

        if (trim($notes) === '') {
            throw new HrRuleViolated(__('Say what happened.'));
        }

        return $this->transaction(function () use ($employee, $action, $effectiveOn, $notes, $userId): Employee {
            $this->history->record($employee, HistoryEvent::Disciplinary, $effectiveOn, ['action' => $action->value], $notes, $userId);

            return $employee;
        });
    }
}
