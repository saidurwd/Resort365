<?php

namespace Modules\Restaurant\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The ingredients of a settled bill were taken out of the outlet's store. Accounting books the cost of sales. (Step 5.7)
 */
class SalesConsumed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $propertyId,
        public readonly int $billId,
    ) {}
}
