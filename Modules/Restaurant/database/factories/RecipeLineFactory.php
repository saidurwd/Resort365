<?php

namespace Modules\Restaurant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Restaurant\Database\Factories\Concerns\ResolvesInventoryItem;
use Modules\Restaurant\Models\Recipe;
use Modules\Restaurant\Models\RecipeLine;

/**
 * @extends Factory<RecipeLine>
 */
class RecipeLineFactory extends Factory
{
    use ResolvesInventoryItem;

    protected $model = RecipeLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $recipe = Recipe::factory()->create();

        return ['property_id' => $recipe->property_id, 'recipe_id' => $recipe->id, 'item_id' => $this->inventoryItemId(), 'quantity' => '0.2500', 'waste_percent' => '0', 'sort_order' => 0];
    }
}
