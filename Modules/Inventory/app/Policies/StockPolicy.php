<?php

namespace Modules\Inventory\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Stock (Step 5.3): inventory.stock.view to look at balances, movements and transfers; inventory.stock.adjust for opening
 * balances, adjustments and reversals; inventory.transfer.manage to dispatch, receive and cancel transfers.
 */
class StockPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('inventory.stock.view');
    }

    public function view(Authenticatable&Authorizable $user, mixed $model = null): bool
    {
        return $user->can('inventory.stock.view');
    }

    public function adjust(Authenticatable&Authorizable $user): bool
    {
        return $user->can('inventory.stock.adjust');
    }

    public function issue(Authenticatable&Authorizable $user): bool
    {
        return $user->can('inventory.issue.create');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('inventory.transfer.manage');
    }

    public function update(Authenticatable&Authorizable $user, mixed $model = null): bool
    {
        return $user->can('inventory.transfer.manage');
    }
}
