<?php

namespace Modules\Restaurant\DTOs;

use App\Support\DTOs\Data;
use Modules\Restaurant\Enums\RecipeKind;

/**
 * A recipe to save (Step 5.7): for a menu item (`kind` dish), one of its variants, a modifier, or a named sub-recipe with
 * its yield. Each line is an inventory item or a sub-recipe, with a quantity per portion and a waste percent.
 */
final readonly class NewRecipe extends Data
{
    /**
     * @param  list<array{item_id?: int|null, sub_recipe_id?: int|null, quantity: string, waste_percent?: string|null}>  $lines
     */
    public function __construct(
        public int $propertyId,
        public RecipeKind $kind,
        public array $lines,
        public ?int $menuItemId = null,
        public ?int $variantId = null,
        public ?int $modifierId = null,
        public ?int $recipeId = null,
        public ?string $name = null,
        public string $yieldQuantity = '1',
        public ?string $yieldUnit = null,
        public ?string $notes = null,
    ) {}
}
