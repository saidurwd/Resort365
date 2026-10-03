<?php

namespace Modules\Rates\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Rates\Database\Factories\Concerns\ResolvesProperty;
use Modules\Rates\Enums\SeasonColor;
use Modules\Rates\Models\Season;

/**
 * @extends Factory<Season>
 */
class SeasonFactory extends Factory
{
    use ResolvesProperty;

    protected $model = Season::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'name' => fake()->randomElement(['Peak', 'Shoulder', 'Low', 'Festival']).' '.fake()->unique()->numberBetween(1, 99999),
            'color' => fake()->randomElement(SeasonColor::cases()),
            'priority' => fake()->numberBetween(1, 50),
            'is_active' => true,
        ];
    }
}
