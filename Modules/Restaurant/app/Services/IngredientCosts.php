<?php

namespace Modules\Restaurant\Services;

use Modules\Inventory\Contracts\ItemCatalog;
use Modules\Inventory\Contracts\StockMovementContract;

/**
 * What an ingredient costs per base unit in a store (Step 5.7): the store's average cost when it holds the item at a cost,
 * else the item's standard cost, else nothing.
 */
class IngredientCosts
{
    public function __construct(
        private readonly StockMovementContract $stock,
        private readonly ItemCatalog $items,
    ) {}

    /**
     * @param  iterable<int>  $itemIds
     * @return array<int, string> item id => cost per base unit
     */
    public function forStore(?int $storeId, iterable $itemIds): array
    {
        $ids = collect($itemIds)->unique()->values()->all();
        $known = $this->items->many($ids);
        $costs = [];

        foreach ($ids as $itemId) {
            $average = $storeId !== null ? $this->stock->balance($storeId, $itemId)->avgCost : '0';
            $costs[$itemId] = bccomp($average, '0', 4) > 0 ? $average : ($known[$itemId]->standardCost ?? '0');
        }

        return $costs;
    }
}
