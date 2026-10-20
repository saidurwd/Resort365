<?php

namespace Modules\Payroll\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Service charge and tips pools: payroll.pool.view to look, payroll.pool.manage to work out, send to payroll, open again,
 * delete and set who takes part.
 */
class ServiceChargePoolPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('payroll.pool.view');
    }

    public function view(Authenticatable&Authorizable $user, mixed $pool = null): bool
    {
        return $user->can('payroll.pool.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('payroll.pool.manage');
    }

    public function update(Authenticatable&Authorizable $user, mixed $pool = null): bool
    {
        return $user->can('payroll.pool.manage');
    }

    public function delete(Authenticatable&Authorizable $user, mixed $pool = null): bool
    {
        return $user->can('payroll.pool.manage');
    }
}
