<?php

namespace Modules\Payroll\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * EmployeeSalary: payroll.salary.view to look, payroll.salary.manage to change.
 */
class EmployeeSalaryPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('payroll.salary.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('payroll.salary.manage');
    }

    public function update(Authenticatable&Authorizable $user, mixed $record = null): bool
    {
        return $user->can('payroll.salary.manage');
    }

    public function delete(Authenticatable&Authorizable $user, mixed $record = null): bool
    {
        return $user->can('payroll.salary.manage');
    }
}
