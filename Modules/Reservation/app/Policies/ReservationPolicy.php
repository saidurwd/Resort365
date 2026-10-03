<?php

namespace Modules\Reservation\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Reservation\Models\Reservation;

/**
 * Reservations of properties the user cannot access never load (BelongsToProperty).
 */
class ReservationPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('reservation.booking.view');
    }

    public function view(Authenticatable&Authorizable $user, Reservation $reservation): bool
    {
        return $user->can('reservation.booking.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('reservation.booking.create');
    }
}
