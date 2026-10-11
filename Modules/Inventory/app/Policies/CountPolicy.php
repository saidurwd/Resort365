<?php

namespace Modules\Inventory\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Physical counts (Step 5.4): inventory.count.view to look, inventory.count.manage to start, enter, complete and cancel.
 */
class CountPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('inventory.count.view');
    }

    public function view(Authenticatable&Authorizable $user, mixed $count = null): bool
    {
        return $user->can('inventory.count.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('inventory.count.manage');
    }

    public function update(Authenticatable&Authorizable $user, mixed $count = null): bool
    {
        return $user->can('inventory.count.manage');
    }
}
