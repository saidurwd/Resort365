<?php

namespace Modules\Inventory\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Inventory\Models\StockRequisition;

/**
 * Store requisitions (Step 5.4): inventory.requisition.view to look, .create to ask, .issue to issue an approved one
 * and to make direct issues' sibling screens; the requester (or an issuer) can withdraw.
 */
class RequisitionPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('inventory.requisition.view');
    }

    public function view(Authenticatable&Authorizable $user, StockRequisition $requisition): bool
    {
        return $user->can('inventory.requisition.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('inventory.requisition.create');
    }

    public function issue(Authenticatable&Authorizable $user, StockRequisition $requisition): bool
    {
        return $user->can('inventory.requisition.issue');
    }

    public function withdraw(Authenticatable&Authorizable $user, StockRequisition $requisition): bool
    {
        return $requisition->requested_by === (int) $user->getAuthIdentifier() || $user->can('inventory.requisition.issue');
    }
}
