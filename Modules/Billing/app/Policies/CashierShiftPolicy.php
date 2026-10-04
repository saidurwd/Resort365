<?php

namespace Modules\Billing\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Billing\Models\CashierShift;

/**
 * A cashier sees and closes their own shift; billing.shift.view sees every shift. Shifts of
 * properties the user cannot access never load (BelongsToProperty).
 */
class CashierShiftPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('billing.shift.view');
    }

    public function view(Authenticatable&Authorizable $user, CashierShift $shift): bool
    {
        return $user->can('billing.shift.view') || ($shift->user_id === (int) $user->getAuthIdentifier() && $user->can('billing.shift.open'));
    }

    public function open(Authenticatable&Authorizable $user): bool
    {
        return $user->can('billing.shift.open');
    }

    public function close(Authenticatable&Authorizable $user, CashierShift $shift): bool
    {
        return $shift->isOpen() && $shift->user_id === (int) $user->getAuthIdentifier() && $user->can('billing.shift.open');
    }
}
