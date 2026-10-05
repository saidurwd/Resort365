<?php

namespace Modules\Restaurant\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Restaurant\Models\PosBill;
use Modules\Restaurant\Services\OutletAccess;

/**
 * Restaurant bills: anyone who takes orders in the outlet sees and prints them; taking payments needs
 * restaurant.bill.settle. Reopening, complimentary bills and voids also need their permission or a
 * manager's approval (the Actions check).
 */
class PosBillPolicy
{
    public function __construct(
        private readonly OutletAccess $outlets,
    ) {}

    public function view(Authenticatable&Authorizable $user, PosBill $bill): bool
    {
        return ($user->can('restaurant.order.take') || $user->can('restaurant.bill.settle')) && $this->outlets->canUse((int) $user->getAuthIdentifier(), $bill->outlet_id);
    }

    public function settle(Authenticatable&Authorizable $user, PosBill $bill): bool
    {
        return $user->can('restaurant.bill.settle') && $this->outlets->canUse((int) $user->getAuthIdentifier(), $bill->outlet_id);
    }
}
