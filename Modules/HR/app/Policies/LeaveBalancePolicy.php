<?php

namespace Modules\HR\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Leave balances: hr.leave.view to look, hr.leave.manage to adjust.
 */
class LeaveBalancePolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('hr.leave.view');
    }

    public function adjust(Authenticatable&Authorizable $user): bool
    {
        return $user->can('hr.leave.manage');
    }
}
