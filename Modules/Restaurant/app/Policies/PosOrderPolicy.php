<?php

namespace Modules\Restaurant\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Restaurant\Models\PosOrder;
use Modules\Restaurant\Services\OutletAccess;

/**
 * POS orders: restaurant.order.take, in an outlet the person works in (OutletAccess). Voiding a sent
 * line also needs restaurant.order.void or a manager's approval (VoidOrderLine).
 */
class PosOrderPolicy
{
    public function __construct(
        private readonly OutletAccess $outlets,
    ) {}

    public function update(Authenticatable&Authorizable $user, PosOrder $order): bool
    {
        return $user->can('restaurant.order.take') && $this->outlets->canUse((int) $user->getAuthIdentifier(), $order->outlet_id);
    }
}
