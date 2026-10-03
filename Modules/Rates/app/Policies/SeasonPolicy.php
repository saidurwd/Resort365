<?php

namespace Modules\Rates\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Rates\Models\Season;

/**
 * Seasons of properties the user cannot access never load (BelongsToProperty).
 */
class SeasonPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('rates.season.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('rates.season.manage');
    }

    public function update(Authenticatable&Authorizable $user, Season $season): bool
    {
        return $user->can('rates.season.manage');
    }

    public function delete(Authenticatable&Authorizable $user, Season $season): bool
    {
        return $user->can('rates.season.manage');
    }
}
