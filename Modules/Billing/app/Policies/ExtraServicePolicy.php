<?php

namespace Modules\Billing\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Billing\Models\ExtraService;

class ExtraServicePolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('billing.extra-service.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('billing.extra-service.manage');
    }

    public function update(Authenticatable&Authorizable $user, ExtraService $service): bool
    {
        return $user->can('billing.extra-service.manage');
    }

    public function delete(Authenticatable&Authorizable $user, ExtraService $service): bool
    {
        return $user->can('billing.extra-service.manage');
    }
}
