<?php

namespace Modules\Payroll\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Payroll runs: payroll.run.view to look, .create to make, calculate, adjust, delete and send for approval, .pay to record
 * the payment, .export for the bank file and the register download, payroll.payslip.email to send the payslips. Approving is
 * the Approvals module's (payroll.run.approve and payroll.run.approve-large).
 */
class PayrollRunPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('payroll.run.view');
    }

    public function view(Authenticatable&Authorizable $user, mixed $run = null): bool
    {
        return $user->can('payroll.run.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('payroll.run.create');
    }

    public function update(Authenticatable&Authorizable $user, mixed $run = null): bool
    {
        return $user->can('payroll.run.create');
    }

    public function delete(Authenticatable&Authorizable $user, mixed $run = null): bool
    {
        return $user->can('payroll.run.create');
    }

    public function pay(Authenticatable&Authorizable $user, mixed $run = null): bool
    {
        return $user->can('payroll.run.pay');
    }

    public function export(Authenticatable&Authorizable $user, mixed $run = null): bool
    {
        return $user->can('payroll.run.export');
    }

    public function email(Authenticatable&Authorizable $user, mixed $run = null): bool
    {
        return $user->can('payroll.payslip.email');
    }
}
