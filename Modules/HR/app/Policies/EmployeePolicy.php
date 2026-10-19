<?php

namespace Modules\HR\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Employees: hr.employee.view to look and download documents, hr.employee.manage to change, hr.lifecycle.manage for the
 * lifecycle events, hr.employee.view-sensitive for national ID, passport and bank account number.
 */
class EmployeePolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('hr.employee.view');
    }

    public function view(Authenticatable&Authorizable $user, mixed $employee = null): bool
    {
        return $user->can('hr.employee.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('hr.employee.manage');
    }

    public function update(Authenticatable&Authorizable $user, mixed $employee = null): bool
    {
        return $user->can('hr.employee.manage');
    }

    public function viewSensitive(Authenticatable&Authorizable $user, mixed $employee = null): bool
    {
        return $user->can('hr.employee.view-sensitive');
    }

    public function manageLifecycle(Authenticatable&Authorizable $user, mixed $employee = null): bool
    {
        return $user->can('hr.lifecycle.manage');
    }
}
