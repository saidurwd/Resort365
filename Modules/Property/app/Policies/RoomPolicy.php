<?php

namespace Modules\Property\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Property\Models\Room;

/**
 * Rooms are viewed with property.room.view and changed with property.room.manage.
 * Records of properties the user cannot access never load (BelongsToProperty).
 */
class RoomPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('property.room.view');
    }

    public function view(Authenticatable&Authorizable $user, Room $room): bool
    {
        return $user->can('property.room.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('property.room.manage');
    }

    public function update(Authenticatable&Authorizable $user, Room $room): bool
    {
        return $user->can('property.room.manage');
    }

    public function delete(Authenticatable&Authorizable $user, Room $room): bool
    {
        return $user->can('property.room.manage');
    }
}
