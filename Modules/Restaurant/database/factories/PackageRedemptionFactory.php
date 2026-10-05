<?php

namespace Modules\Restaurant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Restaurant\Database\Factories\Concerns\ResolvesProperty;
use Modules\Restaurant\Enums\MealPeriod;
use Modules\Restaurant\Models\PackageRedemption;
use Modules\Restaurant\Models\PosOrder;

/**
 * @extends Factory<PackageRedemption>
 */
class PackageRedemptionFactory extends Factory
{
    use ResolvesProperty;

    protected $model = PackageRedemption::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'outlet_id' => fn (array $attributes): int => $this->outletId((int) $attributes['property_id']),
            'reservation_id' => fake()->numberBetween(1, 999999),
            'reservation_code' => 'R-'.fake()->numberBetween(10000, 99999),
            'guest_name' => fake()->name(),
            'business_date' => now()->toDateString(),
            'meal_period' => MealPeriod::Breakfast,
            'covers_adults' => 2,
            'covers_children' => 0,
            'entitled' => 2,
            'pos_order_id' => fn (array $attributes): int => PosOrder::factory()->create(['property_id' => $attributes['property_id'], 'outlet_id' => $attributes['outlet_id']])->id,
        ];
    }
}
