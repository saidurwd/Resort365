<?php

namespace Modules\Inventory\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Items (Step 5.2): inventory.item.view to look, inventory.item.manage to add and change, inventory.import to import.
 */
class ItemPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('inventory.item.view');
    }

    public function view(Authenticatable&Authorizable $user, mixed $item = null): bool
    {
        return $user->can('inventory.item.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('inventory.item.manage');
    }

    public function update(Authenticatable&Authorizable $user, mixed $item = null): bool
    {
        return $user->can('inventory.item.manage');
    }

    public function import(Authenticatable&Authorizable $user): bool
    {
        return $user->can('inventory.item.import');
    }
}
