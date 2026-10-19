<?php

namespace Modules\HR\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Attendance: hr.attendance.view to look and read the report, .record to enter or remove a day, .import for biometric CSVs.
 * The roster has hr.roster.view and hr.roster.manage.
 */
class AttendancePolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('hr.attendance.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('hr.attendance.record');
    }

    public function delete(Authenticatable&Authorizable $user, mixed $attendance = null): bool
    {
        return $user->can('hr.attendance.record');
    }

    public function import(Authenticatable&Authorizable $user): bool
    {
        return $user->can('hr.attendance.import');
    }
}
