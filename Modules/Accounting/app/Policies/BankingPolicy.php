<?php

namespace Modules\Accounting\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Cash and bank (Step 4.4): accounting.bank.view to look; .manage to keep accounts, transfers, cheques and
 * statements; .reconcile to match lines and complete a reconciliation. One policy for the banking models.
 */
class BankingPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('accounting.bank.view');
    }

    public function view(Authenticatable&Authorizable $user, mixed $model = null): bool
    {
        return $user->can('accounting.bank.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('accounting.bank.manage');
    }

    public function update(Authenticatable&Authorizable $user, mixed $model = null): bool
    {
        return $user->can('accounting.bank.manage');
    }

    public function void(Authenticatable&Authorizable $user, mixed $model = null): bool
    {
        return $user->can('accounting.bank.manage');
    }

    public function reconcile(Authenticatable&Authorizable $user, mixed $model = null): bool
    {
        return $user->can('accounting.bank.reconcile');
    }
}
