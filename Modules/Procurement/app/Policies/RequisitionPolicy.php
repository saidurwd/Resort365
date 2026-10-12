<?php

namespace Modules\Procurement\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Procurement\Models\PurchaseRequisition;

/**
 * Purchase requisitions: procurement.requisition.view to look, .create to raise one (and to withdraw one's own; a
 * buyer who can write orders can withdraw any), procurement.order.create to order an approved one.
 */
class RequisitionPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('procurement.requisition.view');
    }

    public function view(Authenticatable&Authorizable $user, mixed $requisition = null): bool
    {
        return $user->can('procurement.requisition.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('procurement.requisition.create');
    }

    public function withdraw(Authenticatable&Authorizable $user, PurchaseRequisition $requisition): bool
    {
        return $requisition->requested_by === (int) $user->getAuthIdentifier() || $user->can('procurement.order.create');
    }

    public function order(Authenticatable&Authorizable $user, mixed $requisition = null): bool
    {
        return $user->can('procurement.order.create');
    }
}
