<?php

namespace Modules\Restaurant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Restaurant\Database\Factories\Concerns\ResolvesProperty;
use Modules\Restaurant\Enums\RecipeKind;
use Modules\Restaurant\Models\MenuItem;
use Modules\Restaurant\Models\Recipe;

/**
 * @extends Factory<Recipe>
 */
class RecipeFactory extends Factory
{
    use ResolvesProperty;

    protected $model = Recipe::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'kind' => RecipeKind::Dish,
            'menu_item_id' => fn (array $attributes): int => MenuItem::factory()->create(['property_id' => $attributes['property_id']])->id,
            'yield_quantity' => '1',
        ];
    }
}
