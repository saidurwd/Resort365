<?php

namespace Modules\Rates\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Rates\Database\Factories\Concerns\ResolvesProperty;
use Modules\Rates\Models\RateRestriction;

/**
 * Restrictions for every rate plan and type of the property, unless given.
 *
 * @extends Factory<RateRestriction>
 */
class RateRestrictionFactory extends Factory
{
    use ResolvesProperty;

    protected $model = RateRestriction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'rate_plan_id' => null,
            'rateable_type' => null,
            'rateable_id' => null,
            'date' => fake()->dateTimeBetween('now', '+1 year')->format('Y-m-d'),
            'min_stay' => 2,
            'max_stay' => null,
            'closed_to_arrival' => false,
            'closed_to_departure' => false,
            'stop_sell' => false,
        ];
    }
}
