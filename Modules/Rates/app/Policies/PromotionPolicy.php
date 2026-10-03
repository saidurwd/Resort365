<?php

namespace Modules\Rates\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Rates\Models\Promotion;

/**
 * Records of properties the user cannot access never load (BelongsToProperty).
 */
class PromotionPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('rates.promotion.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('rates.promotion.manage');
    }

    public function update(Authenticatable&Authorizable $user, Promotion $promotion): bool
    {
        return $user->can('rates.promotion.manage');
    }

    public function delete(Authenticatable&Authorizable $user, Promotion $promotion): bool
    {
        return $user->can('rates.promotion.manage');
    }
}
