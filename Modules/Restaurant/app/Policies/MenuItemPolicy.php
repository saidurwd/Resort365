<?php

namespace Modules\Restaurant\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Restaurant\Models\MenuItem;

/**
 * The menu: restaurant.menu.view to look (and see item photos), restaurant.menu.manage to change items
 * and upload photos. Items of properties the user cannot access never load (BelongsToProperty).
 */
class MenuItemPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('restaurant.menu.view');
    }

    public function view(Authenticatable&Authorizable $user, MenuItem $item): bool
    {
        return $user->can('restaurant.menu.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('restaurant.menu.manage');
    }

    public function update(Authenticatable&Authorizable $user, MenuItem $item): bool
    {
        return $user->can('restaurant.menu.manage');
    }
}
