<?php

namespace Modules\Reservation\Contracts;

use Modules\Reservation\DTOs\ReservationSummary;

/**
 * Reservations for other modules (Billing). Lookups respect the user's property access.
 */
interface ReservationLookup
{
    public function find(int $reservationId): ?ReservationSummary;
}
