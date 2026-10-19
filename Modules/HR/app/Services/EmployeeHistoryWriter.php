<?php

namespace Modules\HR\Services;

use Modules\HR\Enums\HistoryEvent;
use Modules\HR\Models\Employee;
use Modules\HR\Models\EmployeeHistory;

/**
 * Writes an employee's history (Step 6.1); every lifecycle action goes through here.
 */
class EmployeeHistoryWriter
{
    /**
     * @param  array<string, mixed>|null  $details  before and after values
     */
    public function record(Employee $employee, HistoryEvent $event, string $effectiveOn, ?array $details, ?string $notes, ?int $userId): EmployeeHistory
    {
        return EmployeeHistory::query()->create([
            'employee_id' => $employee->id, 'event' => $event, 'effective_on' => $effectiveOn, 'details' => $details,
            'notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : null, 'recorded_by' => $userId,
        ]);
    }
}
