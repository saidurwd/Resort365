<?php

namespace Modules\Restaurant\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Restaurant\Models\Outlet;

/**
 * Outlet setup: restaurant.outlet.view to look, restaurant.outlet.manage to change outlets, their
 * stations and terminals, restaurant.floor-plan.manage for areas and tables. Outlets of properties
 * the user cannot access never load (BelongsToProperty).
 */
class OutletPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('restaurant.outlet.view');
    }

    public function view(Authenticatable&Authorizable $user, Outlet $outlet): bool
    {
        return $user->can('restaurant.outlet.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('restaurant.outlet.manage');
    }

    public function update(Authenticatable&Authorizable $user, Outlet $outlet): bool
    {
        return $user->can('restaurant.outlet.manage');
    }

    /**
     * The outlet's price list: setup viewers, price managers, and staff who mark items sold out.
     */
    public function viewPrices(Authenticatable&Authorizable $user, Outlet $outlet): bool
    {
        return $user->can('restaurant.outlet.view') || $user->can('restaurant.price.manage') || $user->can('restaurant.menu.mark-sold-out');
    }

    public function markSoldOut(Authenticatable&Authorizable $user, Outlet $outlet): bool
    {
        return $user->can('restaurant.price.manage') || $user->can('restaurant.menu.mark-sold-out');
    }

    public function editFloorPlan(Authenticatable&Authorizable $user, Outlet $outlet): bool
    {
        return $user->can('restaurant.floor-plan.manage');
    }
}
