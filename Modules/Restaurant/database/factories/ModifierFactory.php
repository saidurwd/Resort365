<?php

namespace Modules\Restaurant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Restaurant\Database\Factories\Concerns\ResolvesProperty;
use Modules\Restaurant\Models\Modifier;
use Modules\Restaurant\Models\ModifierGroup;

/**
 * @extends Factory<Modifier>
 */
class ModifierFactory extends Factory
{
    use ResolvesProperty;

    protected $model = Modifier::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'modifier_group_id' => fn (array $attributes): int => ModifierGroup::factory()->create(['property_id' => $attributes['property_id']])->id,
            'name' => fake()->randomElement(['Extra cheese', 'No onion', 'Well done', 'Oat milk']),
            'price_delta' => '0.00',
            'is_active' => true,
        ];
    }
}
