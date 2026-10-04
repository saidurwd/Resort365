<?php

namespace Modules\Housekeeping\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Housekeeping\Models\MaintenanceSchedule;

/**
 * Preventive schedules are kept by housekeeping.schedule.manage.
 */
class MaintenanceSchedulePolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('housekeeping.schedule.manage');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('housekeeping.schedule.manage');
    }

    public function update(Authenticatable&Authorizable $user, MaintenanceSchedule $schedule): bool
    {
        return $user->can('housekeeping.schedule.manage');
    }
}
