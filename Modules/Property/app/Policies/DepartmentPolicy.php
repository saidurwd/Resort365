<?php

namespace Modules\Property\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Property\Models\Department;

/**
 * Departments are viewed with property.department.view and changed with property.department.manage.
 */
class DepartmentPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('property.department.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('property.department.manage');
    }

    public function update(Authenticatable&Authorizable $user, Department $department): bool
    {
        return $user->can('property.department.manage');
    }

    public function delete(Authenticatable&Authorizable $user, Department $department): bool
    {
        return $user->can('property.department.manage');
    }
}
