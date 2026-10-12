<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use InvalidArgumentException;
use Modules\Inventory\Contracts\ItemCatalog;
use Modules\Restaurant\DTOs\NewRecipe;
use Modules\Restaurant\Enums\RecipeKind;
use Modules\Restaurant\Exceptions\RestaurantSetupInvalid;
use Modules\Restaurant\Models\Recipe;
use Modules\Restaurant\Models\RecipeLine;
use Modules\Restaurant\Services\RecipeBook;
use Modules\Restaurant\Services\RecipeExploder;

/**
 * Saves the recipe of a menu item, a variant, a modifier or a sub-recipe (Step 5.7), replacing its lines. A recipe with no
 * lines is removed. Ingredients must be stocked items, quantities above zero, waste below 100%, and a sub-recipe cannot
 * (even through other sub-recipes) contain itself.
 */
class SaveRecipe extends Action
{
    public function __construct(
        private readonly ItemCatalog $items,
        private readonly RecipeExploder $exploder,
        private readonly RecipeBook $book,
    ) {}

    /**
     * @throws RestaurantSetupInvalid
     */
    public function handle(NewRecipe $data): ?Recipe
    {
        $lines = [];

        foreach ($data->lines as $line) {
            $itemId = ($line['item_id'] ?? null) ?: null;
            $subId = ($line['sub_recipe_id'] ?? null) ?: null;

            if ($itemId === null && $subId === null) {
                continue;
            }

            if ($itemId !== null && $subId !== null) {
                throw new RestaurantSetupInvalid(__('A line is an ingredient or a sub-recipe, not both.'));
            }

            $waste = ($line['waste_percent'] ?? '') !== '' ? (string) $line['waste_percent'] : '0';

            if (trim($line['quantity']) === '' || ! BigDecimal::of($line['quantity'])->isPositive() || BigDecimal::of($waste)->isNegative() || BigDecimal::of($waste)->isGreaterThanOrEqualTo('100')) {
                throw new RestaurantSetupInvalid(__('Every ingredient needs a quantity above zero and a waste below 100%.'));
            }

            if ($itemId !== null) {
                $item = $this->items->find($itemId) ?? throw new RestaurantSetupInvalid(__('Unknown ingredient.'));

                if (! $item->isStockable) {
                    throw new RestaurantSetupInvalid(__(':item is not a stocked item, so it cannot be an ingredient.', ['item' => $item->name]));
                }
            }

            $lines[] = ['item_id' => $itemId, 'sub_recipe_id' => $subId, 'quantity' => $line['quantity'], 'waste_percent' => $waste];
        }

        if ($data->kind === RecipeKind::Sub) {
            $name = trim((string) $data->name);

            if ($name === '' || ! BigDecimal::of($data->yieldQuantity)->isPositive()) {
                throw new RestaurantSetupInvalid(__('A sub-recipe needs a name and a yield above zero.'));
            }
        }

        return $this->transaction(function () use ($data, $lines): ?Recipe {
            $recipe = $this->find($data);

            if ($lines === []) {
                if ($recipe instanceof Recipe) {
                    $this->guardUnused($recipe);
                    $recipe->delete();
                }

                return null;
            }

            $recipe ??= new Recipe(['property_id' => $data->propertyId, 'kind' => $data->kind, 'menu_item_id' => $data->menuItemId, 'menu_item_variant_id' => $data->variantId, 'modifier_id' => $data->modifierId]);
            $recipe->fill($data->kind === RecipeKind::Sub
                ? ['name' => trim((string) $data->name), 'yield_quantity' => $data->yieldQuantity, 'yield_unit' => $data->yieldUnit, 'notes' => $data->notes]
                : ['notes' => $data->notes, 'yield_quantity' => '1'])->save();

            $this->guardCycle($recipe, $lines);
            RecipeLine::query()->where('recipe_id', $recipe->id)->delete();

            foreach ($lines as $order => $line) {
                RecipeLine::query()->create(['property_id' => $recipe->property_id, 'recipe_id' => $recipe->id, 'sort_order' => $order, ...$line]);
            }

            return $recipe->load('lines');
        });
    }

    private function find(NewRecipe $data): ?Recipe
    {
        return match ($data->kind) {
            RecipeKind::Dish => Recipe::query()->where('kind', RecipeKind::Dish->value)->where('menu_item_id', $data->menuItemId)->first(),
            RecipeKind::Variant => Recipe::query()->where('kind', RecipeKind::Variant->value)->where('menu_item_id', $data->menuItemId)->where('menu_item_variant_id', $data->variantId)->first(),
            RecipeKind::Modifier => Recipe::query()->where('kind', RecipeKind::Modifier->value)->where('modifier_id', $data->modifierId)->first(),
            RecipeKind::Sub => $data->recipeId !== null ? Recipe::query()->where('kind', RecipeKind::Sub->value)->find($data->recipeId) : null,
        };
    }

    /**
     * @throws RestaurantSetupInvalid
     */
    private function guardUnused(Recipe $recipe): void
    {
        if (RecipeLine::query()->where('sub_recipe_id', $recipe->id)->exists()) {
            throw new RestaurantSetupInvalid(__('":name" is used in other recipes: take it out of them first.', ['name' => $recipe->name]));
        }
    }

    /**
     * @param  list<array{item_id: int|null, sub_recipe_id: int|null, quantity: string, waste_percent: string}>  $lines
     *
     * @throws RestaurantSetupInvalid
     */
    private function guardCycle(Recipe $recipe, array $lines): void
    {
        $recipes = $this->book->all();
        $recipes[$recipe->id] = ['yield' => (string) $recipe->yield_quantity, 'lines' => $lines];

        try {
            $this->exploder->explode($recipes, $recipe->id);
        } catch (InvalidArgumentException $exception) {
            throw new RestaurantSetupInvalid(__('A sub-recipe cannot contain itself, directly or through another one.'), 0, $exception);
        }
    }
}
