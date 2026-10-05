<?php

namespace Modules\Reservation\Services;

/**
 * Which meal periods a meal plan gives on a date of a stay (ARCHITECTURE §5.10.9), without the database:
 * CP breakfast; MAP breakfast and dinner; AP breakfast, lunch and dinner. Breakfast is served the
 * morning after a night stayed (check-in < date ≤ check-out); lunch and dinner on a night being stayed
 * (check-in ≤ date < check-out).
 */
class MealEntitlements
{
    /**
     * @return list<string>
     */
    public function periods(string $mealPlan, string $checkIn, string $checkOut, string $date): array
    {
        $included = match ($mealPlan) {
            'CP' => ['breakfast'],
            'MAP' => ['breakfast', 'dinner'],
            'AP' => ['breakfast', 'lunch', 'dinner'],
            default => [],
        };

        return array_values(array_filter($included, fn (string $period): bool => $period === 'breakfast'
            ? $checkIn < $date && $date <= $checkOut
            : $checkIn <= $date && $date < $checkOut));
    }
}
