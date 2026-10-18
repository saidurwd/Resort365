<?php

namespace Modules\Inventory\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Opening balances were entered for a store (Step 5.8). `$batchId` is the first movement of the run; Accounting books the
 * stock into the ledger against opening balance equity.
 */
class StockOpened implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $propertyId,
        public readonly int $batchId,
    ) {}
}
