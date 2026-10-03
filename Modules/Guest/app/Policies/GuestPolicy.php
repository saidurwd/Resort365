<?php

namespace Modules\Guest\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Guest\Models\Guest;

class GuestPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('guest.guest.view');
    }

    public function view(Authenticatable&Authorizable $user, Guest $guest): bool
    {
        return $user->can('guest.guest.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('guest.guest.create');
    }

    public function update(Authenticatable&Authorizable $user, Guest $guest): bool
    {
        return $user->can('guest.guest.update');
    }

    /**
     * See the full ID number.
     */
    public function viewId(Authenticatable&Authorizable $user, Guest $guest): bool
    {
        return $user->can('guest.guest.view-id');
    }

    /**
     * Download ID documents (Core's attachment routes ask for this instead of `view`).
     */
    public function viewAttachments(Authenticatable&Authorizable $user, Guest $guest): bool
    {
        return $user->can('guest.guest.view-id');
    }

    public function merge(Authenticatable&Authorizable $user, Guest $guest): bool
    {
        return $user->can('guest.guest.merge');
    }

    public function blacklist(Authenticatable&Authorizable $user, Guest $guest): bool
    {
        return $user->can('guest.guest.blacklist');
    }
}
