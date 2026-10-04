<?php

namespace Modules\Restaurant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Restaurant\Database\Factories\Concerns\ResolvesProperty;
use Modules\Restaurant\Models\MenuCategory;

/**
 * @extends Factory<MenuCategory>
 */
class MenuCategoryFactory extends Factory
{
    use ResolvesProperty;

    protected $model = MenuCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'name' => ['en' => fake()->randomElement(['Starters', 'Mains', 'Desserts', 'Drinks']).' '.fake()->unique()->numberBetween(1, 99999)],
            'colour' => 'primary',
            'is_active' => true,
        ];
    }
}
