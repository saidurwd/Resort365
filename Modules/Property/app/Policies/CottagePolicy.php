<?php

namespace Modules\Property\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Property\Models\Cottage;

/**
 * Cottages are viewed with property.cottage.view and changed with property.cottage.manage.
 * Records of properties the user cannot access never load (BelongsToProperty).
 */
class CottagePolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('property.cottage.view');
    }

    public function view(Authenticatable&Authorizable $user, Cottage $cottage): bool
    {
        return $user->can('property.cottage.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('property.cottage.manage');
    }

    public function update(Authenticatable&Authorizable $user, Cottage $cottage): bool
    {
        return $user->can('property.cottage.manage');
    }

    public function delete(Authenticatable&Authorizable $user, Cottage $cottage): bool
    {
        return $user->can('property.cottage.manage');
    }
}
