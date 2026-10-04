<?php

namespace Modules\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Billing\Enums\ChargeCategory;
use Modules\Billing\Models\ChargeCode;

/**
 * @extends Factory<ChargeCode>
 */
class ChargeCodeFactory extends Factory
{
    protected $model = ChargeCode::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'X'.fake()->unique()->numberBetween(100, 99999),
            'name' => fake()->randomElement(['Laundry', 'Spa', 'Minibar', 'Excursion', 'Boat ride']),
            'category' => ChargeCategory::Extra,
            'is_active' => true,
        ];
    }
}
