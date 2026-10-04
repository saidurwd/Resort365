<?php

namespace Modules\Housekeeping\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Housekeeping\Models\HousekeepingTask;

/**
 * Supervisors (housekeeping.task.manage) see every task, assign, create and inspect; attendants
 * (housekeeping.task.perform) start, finish or skip the tasks given to them, or unassigned ones.
 */
class HousekeepingTaskPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('housekeeping.task.manage');
    }

    public function manage(Authenticatable&Authorizable $user): bool
    {
        return $user->can('housekeeping.task.manage');
    }

    public function step(Authenticatable&Authorizable $user, HousekeepingTask $task, string $step): bool
    {
        if (in_array($step, ['pass', 'fail'], true)) {
            return $user->can('housekeeping.task.manage');
        }

        return $user->can('housekeeping.task.manage')
            || ($user->can('housekeeping.task.perform') && in_array($task->assigned_to, [null, (int) $user->getAuthIdentifier()], true));
    }
}
