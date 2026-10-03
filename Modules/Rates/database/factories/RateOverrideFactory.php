<?php

namespace Modules\Rates\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Property\Enums\UnitKind;
use Modules\Rates\Database\Factories\Concerns\ResolvesProperty;
use Modules\Rates\Models\RateOverride;
use Modules\Rates\Models\RatePlan;

/**
 * @extends Factory<RateOverride>
 */
class RateOverrideFactory extends Factory
{
    use ResolvesProperty;

    protected $model = RateOverride::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'rate_plan_id' => fn (array $attributes): int => RatePlan::factory()->create(['property_id' => $attributes['property_id']])->id,
            'rateable_type' => UnitKind::RoomType,
            'rateable_id' => fake()->numberBetween(1, 1000),
            'date' => fake()->unique()->dateTimeBetween('now', '+2 years')->format('Y-m-d'),
            'amount' => '15000.00',
        ];
    }
}
