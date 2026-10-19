<?php

namespace Modules\Payroll\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * SalaryComponent: payroll.component.view to look, payroll.component.manage to change.
 */
class SalaryComponentPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('payroll.component.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('payroll.component.manage');
    }

    public function update(Authenticatable&Authorizable $user, mixed $record = null): bool
    {
        return $user->can('payroll.component.manage');
    }

    public function delete(Authenticatable&Authorizable $user, mixed $record = null): bool
    {
        return $user->can('payroll.component.manage');
    }
}
