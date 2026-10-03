<?php

namespace Modules\Property\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Property\Models\Amenity;

/**
 * The amenities catalogue is viewed with property.amenity.view and changed with property.amenity.manage.
 */
class AmenityPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('property.amenity.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('property.amenity.manage');
    }

    public function update(Authenticatable&Authorizable $user, Amenity $amenity): bool
    {
        return $user->can('property.amenity.manage');
    }

    public function delete(Authenticatable&Authorizable $user, Amenity $amenity): bool
    {
        return $user->can('property.amenity.manage');
    }
}
