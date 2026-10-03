<?php

namespace Modules\Rates\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Rates\Database\Factories\Concerns\ResolvesProperty;
use Modules\Rates\Models\Season;
use Modules\Rates\Models\SeasonPeriod;

/**
 * @extends Factory<SeasonPeriod>
 */
class SeasonPeriodFactory extends Factory
{
    use ResolvesProperty;

    protected $model = SeasonPeriod::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('now', '+1 year');

        return [
            'property_id' => $this->propertyId(...),
            'season_id' => fn (array $attributes): int => Season::factory()->create(['property_id' => $attributes['property_id']])->id,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => (clone $start)->modify('+30 days')->format('Y-m-d'),
        ];
    }
}
