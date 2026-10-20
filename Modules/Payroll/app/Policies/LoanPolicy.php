<?php

namespace Modules\Payroll\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Loans and salary advances: payroll.loan.view to look, payroll.loan.manage to give, move and write off.
 */
class LoanPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('payroll.loan.view');
    }

    public function view(Authenticatable&Authorizable $user, mixed $loan = null): bool
    {
        return $user->can('payroll.loan.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('payroll.loan.manage');
    }

    public function update(Authenticatable&Authorizable $user, mixed $loan = null): bool
    {
        return $user->can('payroll.loan.manage');
    }
}
