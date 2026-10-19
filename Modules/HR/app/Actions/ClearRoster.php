<?php

namespace Modules\HR\Actions;

use App\Support\Actions\Action;
use Modules\HR\Models\Roster;

/**
 * Removes roster entries for employees between two dates (Step 6.2); attendance already recorded stays, and so do days of approved leave.
 */
class ClearRoster extends Action
{
    /**
     * @param  list<int>  $employeeIds
     * @return int the entries removed
     */
    public function handle(array $employeeIds, string $from, string $to): int
    {
        return Roster::query()->whereIn('employee_id', $employeeIds)->whereBetween('work_date', [$from, $to])->whereNull('leave_request_id')->delete();
    }
}
