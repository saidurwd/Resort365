<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Modules\Inventory\Contracts\StockMovementContract;
use Modules\Inventory\DTOs\NewStockMovement;
use Modules\Inventory\Enums\MovementType;
use Modules\Restaurant\Events\SalesConsumptionReversed;
use Modules\Restaurant\Models\PosBill;
use Modules\Restaurant\Models\SaleConsumption;

/**
 * Gives a voided bill's ingredients back to its store (Step 5.7), at the cost they left at. Used when the food was not
 * prepared; once only.
 */
class ReverseBillConsumption extends Action
{
    public function __construct(private readonly StockMovementContract $stock) {}

    /**
     * @return int the number of ingredient rows put back
     */
    public function handle(PosBill $bill, ?int $userId = null): int
    {
        return $this->transaction(function () use ($bill, $userId): int {
            $rows = SaleConsumption::query()->where('pos_bill_id', $bill->id)->whereNull('reversed_at')->lockForUpdate()->get();

            foreach ($rows as $row) {
                $movement = $this->stock->record(new NewStockMovement(
                    MovementType::Reversal, $row->store_id, $row->item_id, (string) $row->quantity, (string) $row->unit_cost, date: $bill->business_date->toDateString(), reason: 'reversal', notes: $bill->bill_no,
                    sourceType: 'pos_bill', sourceId: $bill->id, sourceEvent: 'consumption_reversed', idempotencyKey: 'sale-reversal:'.$row->id, userId: $userId, reverses: $row->stock_movement_id, adds: true,
                ));
                $row->forceFill(['reversal_movement_id' => $movement->id, 'reversed_at' => now()])->save();
            }

            if ($rows->isNotEmpty()) {
                SalesConsumptionReversed::dispatch($bill->tenant_id, $bill->property_id, $bill->id);
            }

            return $rows->count();
        }, attempts: 3);
    }
}
