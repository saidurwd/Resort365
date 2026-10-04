<?php

namespace Modules\Housekeeping\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Housekeeping\Models\MaintenanceRequest;

/**
 * Anyone with housekeeping.work-order.create reports faults and follows their own reports;
 * housekeeping.work-order.view sees every work order; managers (housekeeping.work-order.manage)
 * and the technician it is assigned to work on it.
 */
class MaintenanceRequestPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('housekeeping.work-order.view');
    }

    public function view(Authenticatable&Authorizable $user, MaintenanceRequest $order): bool
    {
        return $user->can('housekeeping.work-order.view') || $order->reported_by === (int) $user->getAuthIdentifier();
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('housekeeping.work-order.create');
    }

    public function update(Authenticatable&Authorizable $user, MaintenanceRequest $order): bool
    {
        return $user->can('housekeeping.work-order.manage')
            || ($user->can('housekeeping.work-order.view') && $order->assigned_to === (int) $user->getAuthIdentifier());
    }
}
