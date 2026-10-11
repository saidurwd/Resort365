<?php

namespace Modules\Inventory\Actions;

use App\Support\Actions\Action;
use Modules\Inventory\Contracts\StockMovementContract;
use Modules\Inventory\DTOs\NewStockMovement;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Enums\TransferStatus;
use Modules\Inventory\Exceptions\StockRuleViolated;
use Modules\Inventory\Models\StockTransfer;
use Modules\Inventory\Models\StockTransferLine;

/**
 * The destination store takes in a transfer in transit (Step 5.3), all its lines at the cost they left the source at.
 */
class ReceiveTransfer extends Action
{
    public function __construct(private readonly StockMovementContract $stock) {}

    /**
     * @throws StockRuleViolated
     */
    public function handle(StockTransfer $transfer, ?int $userId = null): StockTransfer
    {
        return $this->transaction(function () use ($transfer, $userId): StockTransfer {
            $locked = StockTransfer::query()->lockForUpdate()->findOrFail($transfer->id);

            if ($locked->status !== TransferStatus::Dispatched) {
                throw new StockRuleViolated(__('Transfer :no is :status.', ['no' => $locked->transfer_no, 'status' => mb_strtolower($locked->status->label())]));
            }

            foreach (StockTransferLine::query()->where('stock_transfer_id', $locked->id)->get() as $line) {
                $this->stock->record(new NewStockMovement(
                    MovementType::TransferIn, $locked->to_store_id, $line->item_id, (string) $line->quantity, (string) $line->unit_cost, notes: $locked->transfer_no, sourceType: 'stock_transfer', sourceId: $locked->id,
                    sourceEvent: 'received', idempotencyKey: 'transfer:'.$locked->id.':in:'.$line->item_id, userId: $userId,
                ));
            }

            $locked->forceFill(['status' => TransferStatus::Received, 'received_at' => now(), 'received_by' => $userId])->save();

            return $locked->load('lines');
        }, attempts: 3);
    }
}
