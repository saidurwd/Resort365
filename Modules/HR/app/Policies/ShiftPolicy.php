<?php

namespace Modules\HR\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Shifts: hr.shift.view to look, hr.shift.manage to change.
 */
class ShiftPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('hr.shift.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('hr.shift.manage');
    }

    public function update(Authenticatable&Authorizable $user, mixed $shift = null): bool
    {
        return $user->can('hr.shift.manage');
    }

    public function delete(Authenticatable&Authorizable $user, mixed $shift = null): bool
    {
        return $user->can('hr.shift.manage');
    }
}
