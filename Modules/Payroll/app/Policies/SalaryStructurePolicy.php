<?php

namespace Modules\Payroll\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * SalaryStructure: payroll.structure.view to look, payroll.structure.manage to change.
 */
class SalaryStructurePolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('payroll.structure.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('payroll.structure.manage');
    }

    public function update(Authenticatable&Authorizable $user, mixed $record = null): bool
    {
        return $user->can('payroll.structure.manage');
    }

    public function delete(Authenticatable&Authorizable $user, mixed $record = null): bool
    {
        return $user->can('payroll.structure.manage');
    }
}
