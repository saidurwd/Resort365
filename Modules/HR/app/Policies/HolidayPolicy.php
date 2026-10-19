<?php

namespace Modules\HR\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Holidays: hr.holiday.view to look, hr.holiday.manage to change.
 */
class HolidayPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('hr.holiday.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('hr.holiday.manage');
    }

    public function update(Authenticatable&Authorizable $user, mixed $holiday = null): bool
    {
        return $user->can('hr.holiday.manage');
    }

    public function delete(Authenticatable&Authorizable $user, mixed $holiday = null): bool
    {
        return $user->can('hr.holiday.manage');
    }
}
