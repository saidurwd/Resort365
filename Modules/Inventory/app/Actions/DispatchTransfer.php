<?php

namespace Modules\Inventory\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\Inventory\Contracts\StockMovementContract;
use Modules\Inventory\DTOs\NewStockMovement;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Enums\TransferStatus;
use Modules\Inventory\Exceptions\StockRuleViolated;
use Modules\Inventory\Models\StockTransfer;
use Modules\Inventory\Models\StockTransferLine;
use Modules\Inventory\Models\Store;

/**
 * Sends stock from one store to another of the same property (Step 5.3). It leaves the source at its average cost
 * at once and is in transit until `ReceiveTransfer`, or comes back with `CancelTransfer`. All lines go or none.
 */
class DispatchTransfer extends Action
{
    public function __construct(
        private readonly StockMovementContract $stock,
        private readonly DocumentNumbers $numbers,
    ) {}

    /**
     * @param  list<array{item_id: int, quantity: string}>  $lines
     *
     * @throws StockRuleViolated
     */
    public function handle(int $fromStoreId, int $toStoreId, array $lines, ?string $notes = null, ?int $userId = null): StockTransfer
    {
        $from = Store::query()->find($fromStoreId) ?? throw new StockRuleViolated(__('Choose the store the stock leaves.'));
        $to = Store::query()->find($toStoreId) ?? throw new StockRuleViolated(__('Choose the store the stock goes to.'));

        if ($from->id === $to->id) {
            throw new StockRuleViolated(__('Choose two different stores.'));
        }

        if ($from->property_id !== $to->property_id) {
            throw new StockRuleViolated(__('Stock moves between the stores of one property.'));
        }

        if ($lines === []) {
            throw new StockRuleViolated(__('Add at least one item.'));
        }

        foreach ($lines as $line) {
            if (! BigDecimal::of($line['quantity'])->isPositive()) {
                throw new StockRuleViolated(__('Every quantity must be more than zero.'));
            }
        }

        if (count(array_unique(array_column($lines, 'item_id'))) !== count($lines)) {
            throw new StockRuleViolated(__('An item is listed once per transfer.'));
        }

        return $this->transaction(function () use ($from, $to, $lines, $notes, $userId): StockTransfer {
            $transfer = StockTransfer::query()->create([
                'property_id' => $from->property_id, 'transfer_no' => $this->numbers->next('stock_transfer'), 'from_store_id' => $from->id, 'to_store_id' => $to->id,
                'status' => TransferStatus::Dispatched, 'notes' => $notes, 'dispatched_at' => now(), 'dispatched_by' => $userId,
            ]);

            foreach ($lines as $line) {
                $out = $this->stock->record(new NewStockMovement(
                    MovementType::TransferOut, $from->id, $line['item_id'], $line['quantity'], notes: $transfer->transfer_no, sourceType: 'stock_transfer', sourceId: $transfer->id, sourceEvent: 'dispatched',
                    idempotencyKey: 'transfer:'.$transfer->id.':out:'.$line['item_id'], userId: $userId,
                ));
                StockTransferLine::query()->create(['property_id' => $from->property_id, 'stock_transfer_id' => $transfer->id, 'item_id' => $line['item_id'], 'quantity' => ltrim($out->quantity, '-'), 'unit_cost' => $out->unitCost]);
            }

            return $transfer->load('lines');
        }, attempts: 3);
    }
}
