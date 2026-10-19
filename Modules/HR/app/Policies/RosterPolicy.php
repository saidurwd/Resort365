<?php

namespace Modules\HR\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The roster: hr.roster.view to look, hr.roster.manage to assign, clear and copy.
 */
class RosterPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('hr.roster.view');
    }

    public function manage(Authenticatable&Authorizable $user): bool
    {
        return $user->can('hr.roster.manage');
    }
}
