<?php

namespace Modules\Billing\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Billing\Models\ChargeCode;

class ChargeCodePolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('billing.charge-code.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('billing.charge-code.manage');
    }

    public function update(Authenticatable&Authorizable $user, ChargeCode $code): bool
    {
        return $user->can('billing.charge-code.manage');
    }

    public function delete(Authenticatable&Authorizable $user, ChargeCode $code): bool
    {
        return $user->can('billing.charge-code.manage');
    }
}
