<?php

namespace Modules\Restaurant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Restaurant\Database\Factories\Concerns\ResolvesProperty;
use Modules\Restaurant\Enums\Course;
use Modules\Restaurant\Enums\MenuItemKind;
use Modules\Restaurant\Models\MenuCategory;
use Modules\Restaurant\Models\MenuItem;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    use ResolvesProperty;

    protected $model = MenuItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'menu_category_id' => fn (array $attributes): int => MenuCategory::factory()->create(['property_id' => $attributes['property_id']])->id,
            'code' => 'M'.fake()->unique()->numberBetween(1000, 999999),
            'name' => ['en' => fake()->randomElement(['Chicken curry', 'Prawn malai curry', 'Fried rice', 'Lemonade'])],
            'course' => Course::Main,
            'kind' => MenuItemKind::Dish,
            'dietary_tags' => [],
            'allergens' => [],
            'is_active' => true,
        ];
    }
}
