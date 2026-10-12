<?php

namespace Modules\Procurement\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Vendor bills: procurement.bill.view to look, .create to enter, change, submit and cancel.
 */
class BillPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('procurement.bill.view');
    }

    public function view(Authenticatable&Authorizable $user, mixed $model = null): bool
    {
        return $user->can('procurement.bill.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('procurement.bill.create');
    }

    public function update(Authenticatable&Authorizable $user, mixed $model = null): bool
    {
        return $user->can('procurement.bill.create');
    }
}
