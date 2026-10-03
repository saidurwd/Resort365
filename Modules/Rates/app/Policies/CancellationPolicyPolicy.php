<?php

namespace Modules\Rates\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Rates\Models\CancellationPolicy;

/**
 * Records of properties the user cannot access never load (BelongsToProperty).
 */
class CancellationPolicyPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('rates.policy.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('rates.policy.manage');
    }

    public function update(Authenticatable&Authorizable $user, CancellationPolicy $cancellationPolicy): bool
    {
        return $user->can('rates.policy.manage');
    }

    public function delete(Authenticatable&Authorizable $user, CancellationPolicy $cancellationPolicy): bool
    {
        return $user->can('rates.policy.manage');
    }
}
