<?php

namespace Modules\Procurement\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Vendor payments: procurement.bill.view to look, procurement.payment.create to pay and allocate.
 */
class PaymentPolicy
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
        return $user->can('procurement.payment.create');
    }
}
