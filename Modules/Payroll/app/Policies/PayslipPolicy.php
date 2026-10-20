<?php

namespace Modules\Payroll\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Payslips: payroll.payslip.view to look and download, payroll.run.create to adjust one while the run is open.
 */
class PayslipPolicy
{
    public function view(Authenticatable&Authorizable $user, mixed $payslip = null): bool
    {
        return $user->can('payroll.payslip.view');
    }

    public function update(Authenticatable&Authorizable $user, mixed $payslip = null): bool
    {
        return $user->can('payroll.run.create');
    }
}
