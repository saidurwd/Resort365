<?php

namespace Modules\Reservation\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Modules\Rates\DTOs\RestrictionSet;
use Modules\Reservation\DTOs\RestrictionViolation;
use Modules\Reservation\Enums\StayRestriction;

/**
 * Checks a stay against rate restrictions (ARCHITECTURE §5.5), without database access:
 * stop-sell on any night; closed to arrival, minimum and maximum stay as set on the arrival
 * night; closed to departure as set on the departure date.
 */
class RestrictionChecker
{
    /**
     * @param  array<string, RestrictionSet>  $byDate  date (Y-m-d) => restrictions in force
     * @return list<RestrictionViolation>
     */
    public function check(array $byDate, CarbonImmutable $checkIn, CarbonImmutable $checkOut): array
    {
        $violations = [];
        $nights = (int) $checkIn->diffInDays($checkOut);
        $arrival = $byDate[$checkIn->toDateString()] ?? null;
        $departure = $byDate[$checkOut->toDateString()] ?? null;

        foreach (CarbonPeriod::create($checkIn, $checkOut->subDay()) as $night) {
            if (($byDate[$night->toDateString()] ?? null)?->stopSell) {
                $violations[] = new RestrictionViolation(StayRestriction::StopSell, $night->toDateString());
            }
        }

        if ($arrival?->closedToArrival) {
            $violations[] = new RestrictionViolation(StayRestriction::ClosedToArrival, $checkIn->toDateString());
        }

        if ($departure?->closedToDeparture) {
            $violations[] = new RestrictionViolation(StayRestriction::ClosedToDeparture, $checkOut->toDateString());
        }

        if ($arrival?->minStay !== null && $nights < $arrival->minStay) {
            $violations[] = new RestrictionViolation(StayRestriction::MinStay, $checkIn->toDateString(), $arrival->minStay);
        }

        if ($arrival?->maxStay !== null && $nights > $arrival->maxStay) {
            $violations[] = new RestrictionViolation(StayRestriction::MaxStay, $checkIn->toDateString(), $arrival->maxStay);
        }

        return $violations;
    }
}
