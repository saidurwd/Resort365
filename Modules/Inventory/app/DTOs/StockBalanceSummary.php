<?php

namespace Modules\Inventory\DTOs;

use App\Support\DTOs\Data;

/**
 * What a store holds of an item: quantity in the base unit, weighted-average cost per unit and the value.
 */
final readonly class StockBalanceSummary extends Data
{
    public function __construct(
        public int $storeId,
        public int $itemId,
        public string $quantity,
        public string $avgCost,
        public string $value,
    ) {}
}
