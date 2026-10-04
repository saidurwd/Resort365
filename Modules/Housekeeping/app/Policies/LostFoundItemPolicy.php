<?php

namespace Modules\Housekeeping\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Housekeeping\Models\LostFoundItem;

/**
 * The lost & found register: housekeeping.lost-found.view to see it, .manage to log and close items.
 */
class LostFoundItemPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('housekeeping.lost-found.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('housekeeping.lost-found.manage');
    }

    public function update(Authenticatable&Authorizable $user, LostFoundItem $item): bool
    {
        return $user->can('housekeeping.lost-found.manage');
    }
}
