<?php

namespace Modules\Inventory\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Stores (Step 5.2): inventory.store.view to look, inventory.store.manage to change.
 */
class StorePolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('inventory.store.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('inventory.store.manage');
    }

    public function update(Authenticatable&Authorizable $user, mixed $store = null): bool
    {
        return $user->can('inventory.store.manage');
    }
}
