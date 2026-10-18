<?php

namespace Modules\Inventory\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Inventory\Enums\TransferStatus;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\StockBalance;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\StockTransfer;
use Modules\Inventory\Models\StockTransferLine;
use Modules\Inventory\Models\Store;

/**
 * What stock is worth (Step 5.8): quantity times weighted-average cost per store and item, rounded to two places per row.
 * With no date it reads the live balances; with a date it takes each store-item's last movement up to that day.
 */
class StockValuation
{
    /**
     * @return list<array{store_id: int, store: string, item_id: int, sku: string, name: string, class: string, unit: string, quantity: string, avg_cost: string, value: string}>
     */
    public function rows(int $propertyId, ?string $asOf = null, ?int $storeId = null): array
    {
        $stores = Store::query()->where('property_id', $propertyId)->when($storeId !== null, fn ($query) => $query->whereKey($storeId))->orderBy('name')->get()->keyBy('id');
        $figures = $asOf === null ? $this->live($stores->keys()->all()) : $this->asAt($stores->keys()->all(), $asOf);
        $items = Item::query()->with(['category.parent', 'baseUnit'])->whereIn('id', array_unique(array_column($figures, 'item_id')))->get()->keyBy('id');
        $rows = [];

        foreach ($figures as $figure) {
            $item = $items[$figure['item_id']] ?? null;

            if ($item === null || BigDecimal::of($figure['quantity'])->isZero()) {
                continue;
            }

            $rows[] = [
                'store_id' => $figure['store_id'], 'store' => $stores[$figure['store_id']]->name, 'item_id' => $item->id, 'sku' => $item->sku, 'name' => $item->name, 'class' => $item->category->stockClass()->value,
                'unit' => $item->baseUnit->code, 'quantity' => $figure['quantity'], 'avg_cost' => $figure['avg_cost'], 'value' => (string) BigDecimal::of($figure['quantity'])->multipliedBy($figure['avg_cost'])->toScale(2, RoundingMode::HalfUp),
            ];
        }

        usort($rows, fn (array $a, array $b): int => [$a['store'], $a['name']] <=> [$b['store'], $b['name']]);

        return $rows;
    }

    /**
     * The value per stock class.
     *
     * @return array<string, string>
     */
    public function byClass(int $propertyId, ?string $asOf = null): array
    {
        $totals = [];

        foreach ($this->rows($propertyId, $asOf) as $row) {
            $totals[$row['class']] = (string) BigDecimal::of($totals[$row['class']] ?? '0')->plus($row['value'])->toScale(2);
        }

        return $totals;
    }

    /**
     * Stock on its way between stores: dispatched transfers not yet received, at the cost they left at.
     *
     * @return array<string, string>
     */
    public function inTransit(int $propertyId): array
    {
        $lines = StockTransferLine::query()->with('item.category.parent')->whereIn('stock_transfer_id', StockTransfer::query()->where('property_id', $propertyId)->where('status', TransferStatus::Dispatched->value)->pluck('id'))->get();
        $totals = [];

        foreach ($lines as $line) {
            $class = $line->item->category->stockClass()->value;
            $totals[$class] = (string) BigDecimal::of($totals[$class] ?? '0')->plus(BigDecimal::of($line->quantity)->multipliedBy($line->unit_cost)->toScale(2, RoundingMode::HalfUp))->toScale(2);
        }

        return $totals;
    }

    /**
     * @param  list<int>  $storeIds
     * @return list<array{store_id: int, item_id: int, quantity: string, avg_cost: string}>
     */
    private function live(array $storeIds): array
    {
        return StockBalance::query()->whereIn('store_id', $storeIds)->get()->map(fn (StockBalance $balance): array => ['store_id' => $balance->store_id, 'item_id' => $balance->item_id, 'quantity' => (string) $balance->quantity, 'avg_cost' => (string) $balance->avg_cost])->all();
    }

    /**
     * @param  list<int>  $storeIds
     * @return list<array{store_id: int, item_id: int, quantity: string, avg_cost: string}>
     */
    private function asAt(array $storeIds, string $asOf): array
    {
        $latest = StockMovement::query()->selectRaw('max(id) as id')->whereIn('store_id', $storeIds)->where('movement_date', '<=', $asOf)->groupBy('store_id', 'item_id');

        return StockMovement::query()->whereIn('id', $latest)->get()->map(fn (StockMovement $movement): array => ['store_id' => $movement->store_id, 'item_id' => $movement->item_id, 'quantity' => (string) $movement->quantity_after, 'avg_cost' => (string) $movement->avg_cost_after])->all();
    }
}
