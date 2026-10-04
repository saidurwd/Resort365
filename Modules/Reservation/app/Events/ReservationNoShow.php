<?php

namespace Modules\Reservation\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The night audit marked a reservation as a no-show (ARCHITECTURE §6.7): the arrival night stays
 * booked, later nights are released. fee is the policy's no-show charge; refundDue is what was paid
 * above it (decimal strings).
 */
class ReservationNoShow implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $reservationId,
        public readonly string $fee,
        public readonly string $refundDue,
    ) {}
}
