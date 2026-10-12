<?php

namespace Modules\Inventory\DTOs;

use App\Support\DTOs\Data;

/**
 * An item that sits at or below its reorder level in a store, and how much to buy (Step 5.5). Quantities in the item's
 * base unit, as decimal strings.
 */
final readonly class ReorderSuggestion extends Data
{
    public function __construct(
        public int $storeId,
        public string $storeName,
        public int $itemId,
        public string $sku,
        public string $itemName,
        public string $unit,
        public string $onHand,
        public string $reorderLevel,
        public string $suggestedQuantity,
        public string $unitCost,
    ) {}
}
