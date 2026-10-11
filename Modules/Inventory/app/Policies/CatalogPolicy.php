<?php

namespace Modules\Inventory\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Units and categories (Step 5.2): inventory.item.view to look, inventory.catalog.manage to change.
 */
class CatalogPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('inventory.item.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('inventory.catalog.manage');
    }

    public function update(Authenticatable&Authorizable $user, mixed $model = null): bool
    {
        return $user->can('inventory.catalog.manage');
    }
}
