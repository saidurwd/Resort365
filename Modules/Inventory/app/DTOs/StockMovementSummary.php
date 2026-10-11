<?php

namespace Modules\Inventory\DTOs;

use App\Support\DTOs\Data;
use Modules\Inventory\Enums\MovementType;

/**
 * A recorded stock movement as callers see it: the signed quantity in the base unit, its cost and the balance after it.
 */
final readonly class StockMovementSummary extends Data
{
    public function __construct(
        public int $id,
        public MovementType $type,
        public int $storeId,
        public int $itemId,
        public string $quantity,
        public string $unitCost,
        public string $totalCost,
        public string $quantityAfter,
        public string $avgCostAfter,
        public string $date,
    ) {}
}
