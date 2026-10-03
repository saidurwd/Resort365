<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\TaxCategory;

/**
 * @extends Factory<TaxCategory>
 */
class TaxCategoryFactory extends Factory
{
    protected $model = TaxCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('CAT??##')),
            'name' => fake()->randomElement(['Room', 'Food & beverage', 'Laundry', 'Exempt']),
            'is_active' => true,
        ];
    }
}
