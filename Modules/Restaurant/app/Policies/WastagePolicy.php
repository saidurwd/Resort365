<?php

namespace Modules\Restaurant\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Wastage: restaurant.wastage.view to look, restaurant.wastage.record to write food off.
 */
class WastagePolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('restaurant.wastage.view');
    }

    public function view(Authenticatable&Authorizable $user, mixed $model = null): bool
    {
        return $user->can('restaurant.wastage.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('restaurant.wastage.record');
    }

    public function update(Authenticatable&Authorizable $user, mixed $model = null): bool
    {
        return $user->can('restaurant.wastage.record');
    }
}
