<?php

namespace Modules\Payroll\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * TaxTable: payroll.tax.view to look, payroll.tax.manage to change.
 */
class TaxTablePolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('payroll.tax.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('payroll.tax.manage');
    }

    public function update(Authenticatable&Authorizable $user, mixed $record = null): bool
    {
        return $user->can('payroll.tax.manage');
    }

    public function delete(Authenticatable&Authorizable $user, mixed $record = null): bool
    {
        return $user->can('payroll.tax.manage');
    }
}
