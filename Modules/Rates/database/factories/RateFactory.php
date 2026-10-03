<?php

namespace Modules\Rates\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Property\Enums\UnitKind;
use Modules\Rates\Database\Factories\Concerns\ResolvesProperty;
use Modules\Rates\Models\Rate;
use Modules\Rates\Models\RatePlan;
use Modules\Rates\Support\DaysOfWeek;

/**
 * Base rates (every day) of a rate plan in the same property. Pass rateable_type/rateable_id
 * for a real room or cottage type.
 *
 * @extends Factory<Rate>
 */
class RateFactory extends Factory
{
    use ResolvesProperty;

    protected $model = Rate::class;

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
            'season_id' => null,
            'dow_mask' => DaysOfWeek::EVERY_DAY,
            'amount' => fake()->randomElement([4500, 6000, 8500, 12000]).'.00',
            'extra_adult_amount' => '1500.00',
            'extra_child_amount' => '750.00',
        ];
    }
}
