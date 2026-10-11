<?php

namespace Modules\Inventory\Actions;

use App\Support\Actions\Action;
use Modules\Inventory\Contracts\StockMovementContract;
use Modules\Inventory\DTOs\NewStockMovement;
use Modules\Inventory\DTOs\StockMovementSummary;
use Modules\Inventory\Enums\AdjustmentReason;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Exceptions\StockRuleViolated;

/**
 * Corrects a store's stock by hand (Step 5.3): found stock comes in at the cost given (else the item's standard cost,
 * else the average), lost stock goes out at the average cost. A reason is always recorded.
 */
class AdjustStock extends Action
{
    public function __construct(private readonly StockMovementContract $stock) {}

    /**
     * @throws StockRuleViolated
     */
    public function handle(int $storeId, int $itemId, bool $gain, string $quantity, AdjustmentReason $reason, ?string $notes = null, ?string $unitCost = null, ?int $userId = null, ?string $date = null): StockMovementSummary
    {
        if ($reason === AdjustmentReason::Other && trim((string) $notes) === '') {
            throw new StockRuleViolated(__('Say what happened when the reason is "other".'));
        }

        return $this->stock->record(new NewStockMovement($gain ? MovementType::AdjustmentIn : MovementType::AdjustmentOut, $storeId, $itemId, $quantity, $unitCost, date: $date, reason: $reason->value, notes: $notes, userId: $userId));
    }
}
