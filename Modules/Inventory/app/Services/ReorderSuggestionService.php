<?php

namespace Modules\Inventory\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Inventory\Contracts\ReorderSuggestions;
use Modules\Inventory\DTOs\ReorderSuggestion;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\ItemStoreLevel;
use Modules\Inventory\Models\StockBalance;
use Modules\Inventory\Models\Store;

/**
 * Items at or below their reorder level (the store's level, else the item's), with the quantity to buy: up to the par
 * level when there is one, else the item's reorder quantity, else back to twice the reorder level.
 */
class ReorderSuggestionService implements ReorderSuggestions
{
    public function forProperty(int $propertyId): array
    {
        $items = Item::query()->with('baseUnit')->where('is_stockable', true)->where('is_active', true)->get();
        $levels = ItemStoreLevel::query()->get()->keyBy(fn (ItemStoreLevel $level): string => $level->store_id.':'.$level->item_id);
        $suggestions = [];

        foreach (Store::query()->where('property_id', $propertyId)->where('is_active', true)->orderBy('name')->get() as $store) {
            $balances = StockBalance::query()->where('store_id', $store->id)->get()->keyBy('item_id');
            $found = [];

            foreach ($items as $item) {
                $level = $levels->get($store->id.':'.$item->id);
                $reorder = BigDecimal::of((string) ($level->reorder_level ?? $item->reorder_level));

                if (! $reorder->isPositive()) {
                    continue;
                }

                $balance = $balances->get($item->id);
                $held = BigDecimal::of((string) ($balance->quantity ?? '0'));

                if ($held->isGreaterThan($reorder)) {
                    continue;
                }

                $par = BigDecimal::of((string) ($level->par_level ?? '0'));
                $quantity = match (true) {
                    $par->isGreaterThan($held) => $par->minus($held),
                    BigDecimal::of((string) $item->reorder_qty)->isPositive() => BigDecimal::of((string) $item->reorder_qty),
                    default => $reorder->multipliedBy(2)->minus($held),
                };
                $cost = $balance !== null && BigDecimal::of((string) $balance->avg_cost)->isPositive() ? (string) $balance->avg_cost : (string) ($item->standard_cost ?? '0');
                $found[] = ['gap' => $reorder->minus($held), 'row' => new ReorderSuggestion($store->id, $store->name, $item->id, $item->sku, $item->name, $item->baseUnit->code, (string) $held->toScale(4), (string) $reorder->toScale(4), (string) $quantity->toScale(4, RoundingMode::HalfUp), $cost)];
            }

            usort($found, fn (array $a, array $b): int => $b['gap']->compareTo($a['gap']));

            foreach ($found as $entry) {
                $suggestions[] = $entry['row'];
            }
        }

        return $suggestions;
    }
}
