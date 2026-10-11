<?php

namespace Modules\Inventory\Actions;

use App\Support\Actions\Action;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\Inventory\Enums\CountStatus;
use Modules\Inventory\Exceptions\StockRuleViolated;
use Modules\Inventory\Models\ItemCategory;
use Modules\Inventory\Models\StockBalance;
use Modules\Inventory\Models\StockCount;
use Modules\Inventory\Models\StockCountLine;
use Modules\Inventory\Models\Store;

/**
 * Starts a physical count of a store, or of one category in it (Step 5.4). The system quantity of every stocked item is
 * frozen on the count sheet. Movements go on while counting; completing the count allows for them. One count is open
 * per store.
 */
class StartStockCount extends Action
{
    public function __construct(private readonly DocumentNumbers $numbers) {}

    /**
     * @throws StockRuleViolated
     */
    public function handle(int $storeId, ?int $categoryId = null, bool $blind = false, ?string $notes = null, ?int $userId = null): StockCount
    {
        $store = Store::query()->find($storeId) ?? throw new StockRuleViolated(__('Choose the store to count.'));

        if (StockCount::query()->where('store_id', $store->id)->where('status', CountStatus::Counting->value)->exists()) {
            throw new StockRuleViolated(__(':store has a count in progress: complete or cancel it first.', ['store' => $store->name]));
        }

        $categoryIds = $categoryId !== null ? $this->withDescendants($categoryId) : null;
        $balances = StockBalance::query()->with('item')->where('store_id', $store->id)
            ->when($categoryIds !== null, fn ($query) => $query->whereHas('item', fn ($item) => $item->whereIn('item_category_id', $categoryIds)))->get()
            ->filter(fn (StockBalance $balance): bool => $balance->item->is_stockable && $balance->item->is_active);

        if ($balances->isEmpty()) {
            throw new StockRuleViolated(__('There is nothing in this store to count.'));
        }

        return $this->transaction(function () use ($store, $categoryId, $blind, $notes, $userId, $balances): StockCount {
            $count = StockCount::query()->create([
                'property_id' => $store->property_id, 'count_no' => $this->numbers->next('stock_count'), 'store_id' => $store->id, 'item_category_id' => $categoryId, 'status' => CountStatus::Counting,
                'blind' => $blind, 'notes' => $notes, 'started_at' => now(), 'started_by' => $userId,
            ]);

            foreach ($balances as $balance) {
                StockCountLine::query()->create([
                    'property_id' => $store->property_id, 'stock_count_id' => $count->id, 'item_id' => $balance->item_id, 'system_quantity' => $balance->quantity, 'unit_cost' => $balance->avg_cost,
                    'snapshot_movement_id' => $balance->last_movement_id ?? 0,
                ]);
            }

            return $count->load('lines');
        });
    }

    /**
     * @return list<int>
     */
    private function withDescendants(int $categoryId): array
    {
        $ids = [$categoryId];
        $pending = [$categoryId];

        while ($pending !== []) {
            $children = ItemCategory::query()->whereIn('parent_id', $pending)->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $pending = array_values(array_diff($children, $ids));
            $ids = [...$ids, ...$pending];
        }

        return $ids;
    }
}
