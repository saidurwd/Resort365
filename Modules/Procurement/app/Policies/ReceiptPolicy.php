<?php

namespace Modules\Procurement\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Goods receipts: procurement.receipt.view to look, .create to receive goods.
 */
class ReceiptPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('procurement.receipt.view');
    }

    public function view(Authenticatable&Authorizable $user, mixed $model = null): bool
    {
        return $user->can('procurement.receipt.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('procurement.receipt.create');
    }
}
