<?php

namespace Modules\Restaurant\Services;

use Modules\Core\Contracts\Settings;
use Modules\Restaurant\Enums\MenuItemKind;
use Modules\Restaurant\Models\MenuItem;
use Modules\Restaurant\Models\MenuItemVariant;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Models\OutletMenuItem;

/**
 * Plate cost, food cost % and the suggested price of what an outlet sells (Step 5.7): each priced item (and variant)
 * costed from its recipe at the outlet's store.
 */
class FoodCosting
{
    public function __construct(
        private readonly RecipeBook $book,
        private readonly IngredientCosts $costs,
        private readonly PlateCostCalculator $calculator,
        private readonly Settings $settings,
    ) {}

    /**
     * @return list<array{item: MenuItem, variant: string|null, outlet: Outlet, price: string, has_recipe: bool, cost: string|null, percent: string|null, suggested: string|null}>
     */
    public function rows(int $propertyId, ?int $outletId = null): array
    {
        $target = (string) $this->settings->get('restaurant.target_food_cost_percent', $propertyId);
        $outlets = Outlet::query()->where('property_id', $propertyId)->when($outletId !== null, fn ($query) => $query->whereKey($outletId))->orderBy('name')->get()->keyBy('id');
        $items = MenuItem::query()->where('property_id', $propertyId)->where('is_active', true)->get()->keyBy('id');
        $variants = MenuItemVariant::query()->whereIn('menu_item_id', $items->keys())->get()->keyBy('id');
        $rows = [];

        foreach (OutletMenuItem::query()->whereIn('outlet_id', $outlets->keys())->whereIn('menu_item_id', $items->keys())->orderBy('outlet_id')->orderBy('menu_item_id')->orderBy('variant_key')->get() as $price) {
            $item = $items[$price->menu_item_id];
            $outlet = $outlets[$price->outlet_id];

            if ($item->kind === MenuItemKind::Open) {
                continue;
            }

            $portion = $this->book->portion($item, $price->menu_item_variant_id);
            $cost = $portion === [] ? null : $this->calculator->cost($portion, $this->costs->forStore($outlet->store_id, array_keys($portion)));
            $rows[] = [
                'item' => $item, 'variant' => $price->menu_item_variant_id !== null ? ($variants[$price->menu_item_variant_id]->name ?? null) : null, 'outlet' => $outlet, 'price' => (string) $price->price,
                'has_recipe' => $portion !== [], 'cost' => $cost, 'percent' => $cost !== null ? $this->calculator->percent($cost, (string) $price->price) : null, 'suggested' => $cost !== null ? $this->calculator->suggestedPrice($cost, $target) : null,
            ];
        }

        return $rows;
    }

    /**
     * The cost of one portion at a store, for the recipe page.
     *
     * @return array{cost: string, lines: list<array{item_id: int, quantity: string, unit_cost: string}>}|null
     */
    public function plate(MenuItem $item, ?int $variantId, ?int $storeId): ?array
    {
        $portion = $this->book->portion($item, $variantId);

        if ($portion === []) {
            return null;
        }

        $costs = $this->costs->forStore($storeId, array_keys($portion));

        return [
            'cost' => $this->calculator->cost($portion, $costs),
            'lines' => collect($portion)->map(fn (string $quantity, int $itemId): array => ['item_id' => $itemId, 'quantity' => $quantity, 'unit_cost' => $costs[$itemId] ?? '0'])->values()->all(),
        ];
    }
}
