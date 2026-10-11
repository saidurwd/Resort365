<?php

namespace Modules\Inventory\DTOs;

use App\Support\DTOs\Data;

/**
 * An item as other modules see it (Step 5.2). Quantities and costs are decimal strings in the base unit.
 */
final readonly class ItemSummary extends Data
{
    public function __construct(
        public int $id,
        public string $sku,
        public string $name,
        public string $category,
        public int $baseUnitId,
        public string $baseUnit,
        public bool $isStockable,
        public bool $trackBatches,
        public bool $trackExpiry,
        public string $reorderLevel,
        public string $reorderQty,
        public ?string $standardCost,
        public bool $isActive,
    ) {}
}
