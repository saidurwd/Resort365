<?php

namespace Modules\Restaurant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Restaurant\Database\Factories\Concerns\ResolvesProperty;
use Modules\Restaurant\Enums\MealPeriod;
use Modules\Restaurant\Models\MealEntitlementSnapshot;

/**
 * @extends Factory<MealEntitlementSnapshot>
 */
class MealEntitlementSnapshotFactory extends Factory
{
    use ResolvesProperty;

    protected $model = MealEntitlementSnapshot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'business_date' => now()->toDateString(),
            'reservation_id' => fake()->numberBetween(1, 999999),
            'meal_period' => MealPeriod::Breakfast,
            'covers' => 2,
        ];
    }
}
