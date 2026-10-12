<?php

namespace Modules\Restaurant\Services;

use Modules\Restaurant\Enums\MenuItemKind;
use Modules\Restaurant\Enums\RecipeKind;
use Modules\Restaurant\Models\ComboComponent;
use Modules\Restaurant\Models\MenuItem;
use Modules\Restaurant\Models\Modifier;
use Modules\Restaurant\Models\PosOrderLine;
use Modules\Restaurant\Models\Recipe;

/**
 * The recipes of a property as the exploder needs them, and the ingredients of what was sold (Step 5.7): a variant's
 * recipe replaces the item's, a modifier's recipe adds to it, a direct-stock item is one unit of its inventory item, and a
 * combo is its components. Loaded once per instance.
 */
class RecipeBook
{
    /** @var array<int, array{yield: string, lines: list<array{item_id: int|null, sub_recipe_id: int|null, quantity: string, waste_percent: string}>}>|null */
    private ?array $recipes = null;

    /** @var array<string, int> "kind:key" => recipe id */
    private array $index = [];

    public function __construct(private readonly RecipeExploder $exploder) {}

    /**
     * Ingredients of one portion: item id => quantity.
     *
     * @param  list<int>  $modifierIds
     * @return array<int, string>
     */
    public function portion(MenuItem $item, ?int $variantId = null, array $modifierIds = []): array
    {
        $this->load();

        if ($item->kind === MenuItemKind::Combo) {
            $total = [];

            foreach (ComboComponent::query()->where('menu_item_id', $item->id)->with('item')->get() as $component) {
                $each = $this->portion($component->item, $component->component_variant_id);

                foreach ($each as $itemId => $quantity) {
                    $total[$itemId] = $this->add($total[$itemId] ?? '0', bcmul($quantity, (string) $component->quantity, 4));
                }
            }

            return $total;
        }

        $total = [];
        $base = ($variantId !== null ? $this->index['variant:'.$item->id.':'.$variantId] ?? null : null) ?? $this->index['dish:'.$item->id] ?? null;

        if ($base !== null) {
            $total = $this->exploder->explode($this->recipes ?? [], $base);
        } elseif ($item->kind === MenuItemKind::DirectStock && $item->inventory_item_id !== null) {
            $total = [$item->inventory_item_id => '1.0000'];
        }

        foreach ($modifierIds as $modifierId) {
            foreach (isset($this->index['modifier:'.$modifierId]) ? $this->exploder->explode($this->recipes ?? [], $this->index['modifier:'.$modifierId]) : [] as $itemId => $quantity) {
                $total[$itemId] = $this->add($total[$itemId] ?? '0', $quantity);
            }
        }

        return $total;
    }

    /**
     * The modifiers chosen on an order line (by id, else by name among the item's groups, for lines taken before ids were kept).
     *
     * @return list<int>
     */
    public function modifierIds(PosOrderLine $line): array
    {
        if ($line->modifier_ids !== null) {
            return array_map(intval(...), $line->modifier_ids);
        }

        $ids = [];
        $byName = null;

        foreach ((array) $line->modifiers as $modifier) {
            $byName ??= Modifier::query()->whereIn('modifier_group_id', MenuItem::query()->findOrFail($line->menu_item_id)->modifierGroups()->pluck('modifier_groups.id'))->get()->groupBy('name');
            $found = $byName->get($modifier['name'])?->first();

            if ($found instanceof Modifier) {
                $ids[] = $found->id;
            }
        }

        return $ids;
    }

    public function hasRecipe(int $menuItemId): bool
    {
        $this->load();

        return isset($this->index['dish:'.$menuItemId]);
    }

    /**
     * @return array<int, array{yield: string, lines: list<array{item_id: int|null, sub_recipe_id: int|null, quantity: string, waste_percent: string}>}>
     */
    public function all(): array
    {
        $this->load();

        return $this->recipes ?? [];
    }

    public function recipeId(RecipeKind $kind, int $key, ?int $variantKey = null): ?int
    {
        $this->load();

        return $this->index[$kind->value.':'.$key.($variantKey !== null ? ':'.$variantKey : '')] ?? null;
    }

    private function load(): void
    {
        if ($this->recipes !== null) {
            return;
        }

        $this->recipes = [];

        foreach (Recipe::query()->with('lines')->get() as $recipe) {
            $this->recipes[$recipe->id] = [
                'yield' => (string) $recipe->yield_quantity,
                'lines' => $recipe->lines->map(fn ($line): array => ['item_id' => $line->item_id, 'sub_recipe_id' => $line->sub_recipe_id, 'quantity' => (string) $line->quantity, 'waste_percent' => (string) $line->waste_percent])->all(),
            ];
            $key = match ($recipe->kind) {
                RecipeKind::Dish => 'dish:'.$recipe->menu_item_id,
                RecipeKind::Variant => 'variant:'.$recipe->menu_item_id.':'.$recipe->menu_item_variant_id,
                RecipeKind::Modifier => 'modifier:'.$recipe->modifier_id,
                RecipeKind::Sub => 'sub:'.$recipe->id,
            };
            $this->index[$key] = $recipe->id;
        }
    }

    private function add(string $a, string $b): string
    {
        return bcadd($a, $b, 4);
    }
}
