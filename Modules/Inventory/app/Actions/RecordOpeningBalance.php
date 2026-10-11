<?php

namespace Modules\Inventory\Actions;

use App\Support\Actions\Action;
use Modules\Inventory\Contracts\StockMovementContract;
use Modules\Inventory\DTOs\NewStockMovement;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Exceptions\StockRuleViolated;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\StockMovement;

/**
 * Sets what a store held when the system started, per item with its cost (Step 5.3). An item and store get one opening
 * balance: if it has moved already, correct it with an adjustment. Several items go in one transaction.
 */
class RecordOpeningBalance extends Action
{
    public function __construct(private readonly StockMovementContract $stock) {}

    /**
     * @param  list<array{item_id: int, quantity: string, unit_cost: string}>  $lines
     *
     * @throws StockRuleViolated
     */
    public function handle(int $storeId, array $lines, ?int $userId = null, ?string $date = null): int
    {
        if ($lines === []) {
            throw new StockRuleViolated(__('Enter at least one item.'));
        }

        return $this->transaction(function () use ($storeId, $lines, $userId, $date): int {
            $seen = [];

            foreach ($lines as $line) {
                $item = Item::query()->find($line['item_id']) ?? throw new StockRuleViolated(__('Unknown item.'));

                if (isset($seen[$item->id])) {
                    throw new StockRuleViolated(__(':item is listed twice.', ['item' => $item->name]));
                }

                $seen[$item->id] = true;

                if (StockMovement::query()->where('store_id', $storeId)->where('item_id', $item->id)->exists()) {
                    throw new StockRuleViolated(__(':item has moved in this store already: correct it with an adjustment.', ['item' => $item->name]));
                }

                $this->stock->record(new NewStockMovement(MovementType::Opening, $storeId, $item->id, $line['quantity'], $line['unit_cost'], date: $date, reason: 'opening', userId: $userId));
            }

            return count($lines);
        });
    }
}
