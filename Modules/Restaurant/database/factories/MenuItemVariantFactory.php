<?php

namespace Modules\Restaurant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Restaurant\Database\Factories\Concerns\ResolvesProperty;
use Modules\Restaurant\Models\MenuItem;
use Modules\Restaurant\Models\MenuItemVariant;

/**
 * @extends Factory<MenuItemVariant>
 */
class MenuItemVariantFactory extends Factory
{
    use ResolvesProperty;

    protected $model = MenuItemVariant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'menu_item_id' => fn (array $attributes): int => MenuItem::factory()->create(['property_id' => $attributes['property_id']])->id,
            'name' => fake()->unique()->randomElement(['Half', 'Full', 'Glass', 'Bottle', 'Small', 'Large', 'Regular', 'Jug']).' '.fake()->unique()->numberBetween(1, 99999),
        ];
    }
}
