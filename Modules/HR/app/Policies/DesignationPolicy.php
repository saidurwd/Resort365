<?php

namespace Modules\HR\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Designations: hr.employee.view to look, hr.setup.manage to change.
 */
class DesignationPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('hr.employee.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('hr.setup.manage');
    }

    public function update(Authenticatable&Authorizable $user, mixed $record = null): bool
    {
        return $user->can('hr.setup.manage');
    }

    public function delete(Authenticatable&Authorizable $user, mixed $record = null): bool
    {
        return $user->can('hr.setup.manage');
    }
}
