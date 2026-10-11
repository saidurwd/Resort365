<?php

namespace Modules\Inventory\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A store's stock of an item fell to or below its reorder level (Step 5.3). Procurement will suggest a requisition.
 */
class StockBelowReorderLevel implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $propertyId,
        public readonly int $storeId,
        public readonly int $itemId,
        public readonly string $quantity,
        public readonly string $reorderLevel,
    ) {}
}
