<?php

namespace Modules\Inventory\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Stock was adjusted by hand or by a count (Step 5.4). `sourceType` is `stock_movement` (one adjustment) or `stock_count`.
 * Accounting posts the shrinkage or gain.
 */
class StockAdjusted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $propertyId,
        public readonly string $sourceType,
        public readonly int $sourceId,
    ) {}
}
