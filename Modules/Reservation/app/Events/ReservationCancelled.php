<?php

namespace Modules\Reservation\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A reservation was cancelled and its rooms released (ARCHITECTURE §6.7). fee is what the policy
 * keeps; refundDue is what was paid above it (decimal strings). expired = the deposit hold ran out.
 */
class ReservationCancelled implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $reservationId,
        public readonly string $fee,
        public readonly string $refundDue,
        public readonly bool $expired = false,
    ) {}
}
