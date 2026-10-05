<?php

namespace Modules\Restaurant\Services;

use Modules\Core\Contracts\Settings;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Restaurant\Enums\MealPeriod;
use Modules\Restaurant\Models\PackageRedemption;

/**
 * Meal-plan entitlements at the outlets (ARCHITECTURE §5.10.9): what a booking's rate plans include for a
 * meal period on the property's business date (Reservation's mealEntitlement), less what it already
 * took at any outlet; and the meal period running now.
 */
class MealPlans
{
    public function __construct(
        private readonly ReservationLookup $reservations,
        private readonly PropertyDirectory $properties,
        private readonly Settings $settings,
    ) {}

    /**
     * @return array{entitled: int, taken: int, left: int, plans: list<string>}
     */
    public function remaining(int $propertyId, int $reservationId, MealPeriod $period, ?int $exceptOrderId = null): array
    {
        $date = $this->businessDate($propertyId);
        $entitlement = $this->reservations->mealEntitlement($reservationId, $date);
        $entitled = $entitlement->covers($period->value);
        $taken = (int) PackageRedemption::query()->where('reservation_id', $reservationId)->where('business_date', $date)->where('meal_period', $period->value)
            ->when($exceptOrderId !== null, fn ($query) => $query->where('pos_order_id', '!=', $exceptOrderId))
            ->selectRaw('COALESCE(SUM(covers_adults + covers_children), 0) as covers')->value('covers');

        return ['entitled' => $entitled, 'taken' => $taken, 'left' => max(0, $entitled - $taken), 'plans' => $entitlement->mealPlans];
    }

    public function periodNow(int $propertyId): MealPeriod
    {
        $property = $this->properties->find($propertyId);
        $time = now($property->timezone ?? 'UTC')->format('H:i');

        return MealPeriod::at($time, (string) $this->settings->get('restaurant.breakfast_until', $propertyId), (string) $this->settings->get('restaurant.lunch_until', $propertyId));
    }

    public function businessDate(int $propertyId): string
    {
        return $this->properties->find($propertyId)->businessDate ?? now()->toDateString();
    }
}
