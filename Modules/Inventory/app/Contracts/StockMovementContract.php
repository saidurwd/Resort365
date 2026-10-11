<?php

namespace Modules\Inventory\Contracts;

use Modules\Inventory\DTOs\NewStockMovement;
use Modules\Inventory\DTOs\StockBalanceSummary;
use Modules\Inventory\DTOs\StockMovementSummary;
use Modules\Inventory\Exceptions\InsufficientStock;
use Modules\Inventory\Exceptions\StockRuleViolated;
use Modules\Inventory\Exceptions\UnitNotConvertible;

/**
 * The stock ledger for other modules (ARCHITECTURE §5.12, Step 5.3): Restaurant records sale consumption, wastage and
 * production, Housekeeping amenity issues, Procurement receipts and returns. Every movement is atomic with the
 * balance it changes, costs by weighted average, and keeps its source document.
 */
interface StockMovementContract
{
    /**
     * Record a movement. With an idempotency key already used, the earlier movement is returned and nothing is recorded.
     *
     * @throws InsufficientStock
     * @throws StockRuleViolated
     * @throws UnitNotConvertible
     */
    public function record(NewStockMovement $movement): StockMovementSummary;

    /**
     * What the store holds of the item (zero when it never held any).
     */
    public function balance(int $storeId, int $itemId): StockBalanceSummary;
}
