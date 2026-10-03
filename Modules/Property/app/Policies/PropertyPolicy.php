<?php

namespace Modules\Property\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Property\Models\Property;

/**
 * Properties the user cannot access are already invisible (AccessiblePropertyScope).
 */
class PropertyPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('property.property.view');
    }

    public function view(Authenticatable&Authorizable $user, Property $property): bool
    {
        return $user->can('property.property.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('property.property.create');
    }

    public function update(Authenticatable&Authorizable $user, Property $property): bool
    {
        return $user->can('property.property.update');
    }
}
