<?php

namespace Modules\Rates\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Rates\Models\RatePlan;

/**
 * Plans of properties the user cannot access never load (BelongsToProperty). Prices, date
 * prices and restrictions need rates.rate.* (viewRates / manageRates).
 */
class RatePlanPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('rates.rate-plan.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('rates.rate-plan.manage');
    }

    public function update(Authenticatable&Authorizable $user, RatePlan $plan): bool
    {
        return $user->can('rates.rate-plan.manage');
    }

    public function delete(Authenticatable&Authorizable $user, RatePlan $plan): bool
    {
        return $user->can('rates.rate-plan.manage');
    }

    public function viewRates(Authenticatable&Authorizable $user, RatePlan $plan): bool
    {
        return $user->can('rates.rate.view');
    }

    public function manageRates(Authenticatable&Authorizable $user, RatePlan $plan): bool
    {
        return $user->can('rates.rate.manage');
    }
}
