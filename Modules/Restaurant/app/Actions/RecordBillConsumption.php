<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Inventory\Contracts\StockMovementContract;
use Modules\Inventory\DTOs\NewStockMovement;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Exceptions\StockRuleViolated;
use Modules\Restaurant\Enums\BillStatus;
use Modules\Restaurant\Events\SalesConsumed;
use Modules\Restaurant\Models\MenuItem;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Models\PosBill;
use Modules\Restaurant\Models\PosBillLine;
use Modules\Restaurant\Models\PosOrderLine;
use Modules\Restaurant\Models\SaleConsumption;
use Modules\Restaurant\Services\RecipeBook;

/**
 * Takes the ingredients of a settled bill out of the outlet's store (Step 5.7): each bill line's recipe (its variant's
 * recipe, the chosen modifiers' recipes, a direct-stock item's inventory item, a combo's components) times the quantity
 * sold, as `consumption` movements at the store's cost. A sale never fails for want of stock: the balance may go
 * negative, which shows the gap. Doing it again for the same bill adds nothing. Comped and staff-meal bills consume too,
 * flagged so the cost is booked as such.
 */
class RecordBillConsumption extends Action
{
    public function __construct(
        private readonly StockMovementContract $stock,
        private readonly RecipeBook $book,
    ) {}

    /**
     * @return int the number of ingredient rows recorded
     */
    public function handle(PosBill $bill, ?int $userId = null): int
    {
        $outlet = Outlet::query()->find($bill->outlet_id);

        if (! $outlet instanceof Outlet || $outlet->store_id === null || $bill->status !== BillStatus::Settled) {
            return 0;
        }

        return $this->transaction(function () use ($bill, $outlet, $userId): int {
            $recorded = 0;

            foreach (PosBillLine::query()->where('pos_bill_id', $bill->id)->get() as $billLine) {
                $orderLine = PosOrderLine::query()->find($billLine->pos_order_line_id);
                $item = $orderLine instanceof PosOrderLine ? MenuItem::query()->find($orderLine->menu_item_id) : null;

                if (! $orderLine instanceof PosOrderLine || ! $item instanceof MenuItem) {
                    continue;
                }

                $portion = $this->book->portion($item, $orderLine->menu_item_variant_id, $this->book->modifierIds($orderLine));

                foreach ($portion as $itemId => $perPortion) {
                    $quantity = BigDecimal::of($perPortion)->multipliedBy($billLine->quantity)->toScale(4, RoundingMode::HalfUp);

                    if (! $quantity->isPositive() || SaleConsumption::query()->where('pos_bill_line_id', $billLine->id)->where('item_id', $itemId)->exists()) {
                        continue;
                    }

                    try {
                        $movement = $this->stock->record(new NewStockMovement(
                            MovementType::Consumption, $outlet->store_id, $itemId, (string) $quantity, date: $bill->business_date->toDateString(), reason: 'sale', notes: $bill->bill_no,
                            sourceType: 'pos_bill', sourceId: $bill->id, sourceEvent: 'consumed', idempotencyKey: 'sale:'.$billLine->id.':'.$itemId, userId: $userId, allowNegative: true,
                        ));
                    } catch (StockRuleViolated) {
                        continue;
                    }

                    SaleConsumption::query()->create([
                        'property_id' => $bill->property_id, 'outlet_id' => $bill->outlet_id, 'pos_bill_id' => $bill->id, 'pos_bill_line_id' => $billLine->id, 'item_id' => $itemId, 'store_id' => $outlet->store_id,
                        'quantity' => (string) $quantity, 'unit_cost' => $movement->unitCost, 'total_cost' => BigDecimal::of($movement->totalCost)->toScale(2, RoundingMode::HalfUp), 'is_complimentary' => $bill->is_complimentary,
                        'business_date' => $bill->business_date, 'stock_movement_id' => $movement->id,
                    ]);
                    $recorded++;
                }
            }

            if ($recorded > 0) {
                SalesConsumed::dispatch($bill->tenant_id, $bill->property_id, $bill->id);
            }

            return $recorded;
        }, attempts: 3);
    }
}
