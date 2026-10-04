<?php

namespace Modules\Restaurant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Restaurant\Database\Factories\Concerns\ResolvesProperty;
use Modules\Restaurant\Models\ModifierGroup;

/**
 * @extends Factory<ModifierGroup>
 */
class ModifierGroupFactory extends Factory
{
    use ResolvesProperty;

    protected $model = ModifierGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'name' => 'Choice '.fake()->unique()->numberBetween(1, 99999),
            'min_select' => 0,
            'max_select' => 1,
        ];
    }
}
