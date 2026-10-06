<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Restaurant\Enums\MealPeriod;
use Modules\Restaurant\Models\MealEntitlementSnapshot;

/**
 * Keeps what the stays' meal plans included on a business date (ARCHITECTURE §5.10.9, §5.10.14), run by the
 * night audit, so the report can compare meals included with meals taken for days gone by. Running it again
 * for the same date replaces the day.
 */
class SnapshotMealEntitlements extends Action
{
    public function __construct(
        private readonly ReservationLookup $reservations,
    ) {}

    public function handle(int $propertyId, string $businessDate): int
    {
        return $this->transaction(function () use ($propertyId, $businessDate): int {
            MealEntitlementSnapshot::query()->where('property_id', $propertyId)->where('business_date', $businessDate)->delete();
            $count = 0;

            foreach ($this->reservations->mealEntitlements($propertyId, $businessDate) as $entitlement) {
                foreach (MealPeriod::cases() as $period) {
                    if ($entitlement->covers($period->value) > 0) {
                        MealEntitlementSnapshot::query()->create(['property_id' => $propertyId, 'business_date' => $businessDate, 'reservation_id' => $entitlement->reservationId,
                            'meal_period' => $period, 'covers' => $entitlement->covers($period->value)]);
                        $count++;
                    }
                }
            }

            return $count;
        });
    }
}
