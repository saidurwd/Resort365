<?php

namespace Modules\Procurement\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Purchase returns: procurement.return.view to look, .create to return goods.
 */
class ReturnPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('procurement.return.view');
    }

    public function view(Authenticatable&Authorizable $user, mixed $model = null): bool
    {
        return $user->can('procurement.return.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('procurement.return.create');
    }
}
