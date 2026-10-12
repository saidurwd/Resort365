<?php

namespace Modules\Procurement\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * RFQs: procurement.rfq.view to look, .manage to create, send, enter quotations and award.
 */
class RfqPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('procurement.rfq.view');
    }

    public function view(Authenticatable&Authorizable $user, mixed $rfq = null): bool
    {
        return $user->can('procurement.rfq.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('procurement.rfq.manage');
    }

    public function update(Authenticatable&Authorizable $user, mixed $rfq = null): bool
    {
        return $user->can('procurement.rfq.manage');
    }
}
