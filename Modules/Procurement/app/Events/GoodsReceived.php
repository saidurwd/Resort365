<?php

namespace Modules\Procurement\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Goods were received against a purchase order. Inventory has the stock; Accounting debits the inventory and credits GRNI. (Step 5.6)
 */
class GoodsReceived implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $propertyId,
        public readonly int $receiptId,
    ) {}
}
