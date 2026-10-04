<?php

namespace Modules\Housekeeping\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Housekeeping\Models\RoomBlock;

/**
 * Blocks of properties the user cannot access never load (BelongsToProperty).
 */
class RoomBlockPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('housekeeping.block.manage');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('housekeeping.block.manage');
    }

    public function update(Authenticatable&Authorizable $user, RoomBlock $block): bool
    {
        return $user->can('housekeeping.block.manage');
    }
}
