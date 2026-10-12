<?php

namespace Modules\Restaurant\Models;

use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Restaurant\Database\Factories\RecipeLineFactory;

/**
 * One ingredient (an inventory item or a sub-recipe) with its quantity per portion and its waste percent.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $recipe_id
 * @property int|null $item_id
 * @property int|null $sub_recipe_id
 * @property string $quantity
 * @property string $waste_percent
 * @property int $sort_order
 * @property-read Recipe $recipe
 * @property-read Recipe $subRecipe
 */
#[UseFactory(RecipeLineFactory::class)]
#[Fillable(['property_id', 'recipe_id', 'item_id', 'sub_recipe_id', 'quantity', 'waste_percent', 'sort_order'])]
class RecipeLine extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<RecipeLineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'waste_percent' => 'decimal:2', 'sort_order' => 'integer'];
    }

    /**
     * @return BelongsTo<Recipe, $this>
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /**
     * @return BelongsTo<Recipe, $this>
     */
    public function subRecipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class, 'sub_recipe_id');
    }
}
