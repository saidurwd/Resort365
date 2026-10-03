<?php

namespace Modules\Rates\Contracts;

/**
 * Whether bookings still use a rate plan. Rates binds a "no bookings" default; the Reservation
 * module replaces it, since Rates may not read reservations itself (ARCHITECTURE §4.3).
 */
interface RatePlanUsage
{
    /**
     * Whether a booking that has not ended yet (and is not cancelled) uses the plan.
     */
    public function hasFutureBookings(int $ratePlanId): bool;
}
