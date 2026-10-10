<?php

namespace Modules\Accounting\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Accounting\Models\Account;

/**
 * The chart of accounts: accounting.account.view to look, accounting.account.manage to add, change and delete.
 */
class AccountPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('accounting.account.view');
    }

    public function view(Authenticatable&Authorizable $user, Account $account): bool
    {
        return $user->can('accounting.account.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('accounting.account.manage');
    }

    public function update(Authenticatable&Authorizable $user, Account $account): bool
    {
        return $user->can('accounting.account.manage');
    }

    public function delete(Authenticatable&Authorizable $user, Account $account): bool
    {
        return $user->can('accounting.account.manage');
    }
}
