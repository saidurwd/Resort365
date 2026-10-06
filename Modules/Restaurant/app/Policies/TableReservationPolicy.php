<?php

namespace Modules\Restaurant\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Restaurant\Models\TableReservation;
use Modules\Restaurant\Services\OutletAccess;

/**
 * Table reservations: restaurant.reservation.view to look, restaurant.reservation.manage to book, change,
 * cancel and seat, in an outlet the person works in.
 */
class TableReservationPolicy
{
    public function __construct(
        private readonly OutletAccess $outlets,
    ) {}

    public function view(Authenticatable&Authorizable $user, TableReservation $reservation): bool
    {
        return $user->can('restaurant.reservation.view') && $this->outlets->canUse((int) $user->getAuthIdentifier(), $reservation->outlet_id);
    }

    public function update(Authenticatable&Authorizable $user, TableReservation $reservation): bool
    {
        return $user->can('restaurant.reservation.manage') && $this->outlets->canUse((int) $user->getAuthIdentifier(), $reservation->outlet_id);
    }
}
