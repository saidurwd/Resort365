<?php

namespace Modules\HR\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Leave types and policies: hr.leave.view to look, hr.leave-type.manage to change.
 */
class LeaveTypePolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('hr.leave.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('hr.leave-type.manage');
    }

    public function update(Authenticatable&Authorizable $user, mixed $type = null): bool
    {
        return $user->can('hr.leave-type.manage');
    }

    public function delete(Authenticatable&Authorizable $user, mixed $type = null): bool
    {
        return $user->can('hr.leave-type.manage');
    }
}
