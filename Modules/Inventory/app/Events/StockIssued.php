<?php

namespace Modules\Inventory\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Stock was issued from a store to a department (Step 5.4). Accounting charges the department and credits the inventory.
 */
class StockIssued implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $propertyId,
        public readonly int $issueId,
    ) {}
}
