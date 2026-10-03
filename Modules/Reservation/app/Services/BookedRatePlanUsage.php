<?php

namespace Modules\Reservation\Services;

use Modules\Rates\Contracts\RatePlanUsage;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Models\ReservationItem;

/**
 * Rates' RatePlanUsage: a plan is in use while a booking that has not ended (tentative,
 * confirmed or in house) has an item priced with it.
 */
class BookedRatePlanUsage implements RatePlanUsage
{
    public function hasFutureBookings(int $ratePlanId): bool
    {
        return ReservationItem::query()->where('rate_plan_id', $ratePlanId)
            ->where('check_out', '>', now()->toDateString())
            ->whereIn('status', [ReservationStatus::Tentative->value, ReservationStatus::Confirmed->value, ReservationStatus::CheckedIn->value])
            ->exists();
    }
}
