<?php

namespace Modules\Rates\Services;

use Modules\Rates\Contracts\RatePlanUsage;

/**
 * The default until the Reservation module binds the real check.
 */
class NoRatePlanUsage implements RatePlanUsage
{
    public function hasFutureBookings(int $ratePlanId): bool
    {
        return false;
    }
}
