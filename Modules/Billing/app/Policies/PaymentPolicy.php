<?php

namespace Modules\Billing\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Billing\Models\Payment;

/**
 * Payments of properties the user cannot access never load (BelongsToProperty).
 */
class PaymentPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('billing.payment.view');
    }

    public function view(Authenticatable&Authorizable $user, Payment $payment): bool
    {
        return $user->can('billing.payment.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('billing.payment.create');
    }
}
