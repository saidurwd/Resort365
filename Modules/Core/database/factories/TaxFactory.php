<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Enums\TaxType;
use Modules\Core\Models\Tax;

/**
 * @extends Factory<Tax>
 */
class TaxFactory extends Factory
{
    protected $model = Tax::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('TX??##')),
            'name' => fake()->randomElement(['VAT', 'Service charge', 'Tourism levy', 'City tax']),
            'type' => TaxType::Percent,
            'rate' => (string) fake()->randomElement([5, 10, 15]),
            'is_compound' => false,
            'sort_order' => 10,
            'is_active' => true,
        ];
    }

    public function compound(): static
    {
        return $this->state(['is_compound' => true]);
    }

    public function fixed(string $amount): static
    {
        return $this->state(['type' => TaxType::Fixed, 'rate' => $amount]);
    }
}
