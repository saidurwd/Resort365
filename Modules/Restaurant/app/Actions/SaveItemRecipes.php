<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Modules\Restaurant\DTOs\NewRecipe;
use Modules\Restaurant\Enums\RecipeKind;
use Modules\Restaurant\Models\MenuItem;

/**
 * Saves every recipe on a menu item's recipe page together (Step 5.7): the item's own, its variants' and its modifiers'.
 * A section left empty removes that recipe; if one is refused nothing is saved.
 */
class SaveItemRecipes extends Action
{
    public function __construct(private readonly SaveRecipe $save) {}

    /**
     * @param  array<string, list<array{item_id?: int|null, sub_recipe_id?: int|null, quantity: string, waste_percent?: string|null}>>  $sections  "dish", "variant:ID" or "modifier:ID" => lines
     */
    public function handle(MenuItem $item, array $sections): void
    {
        $this->transaction(function () use ($item, $sections): void {
            foreach ($sections as $key => $lines) {
                [$kind, $id] = array_pad(explode(':', $key, 2), 2, null);

                $this->save->handle(match ($kind) {
                    'variant' => new NewRecipe($item->property_id, RecipeKind::Variant, $lines, menuItemId: $item->id, variantId: (int) $id),
                    'modifier' => new NewRecipe($item->property_id, RecipeKind::Modifier, $lines, modifierId: (int) $id),
                    default => new NewRecipe($item->property_id, RecipeKind::Dish, $lines, menuItemId: $item->id),
                });
            }
        });
    }
}
