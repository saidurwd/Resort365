<?php

namespace Modules\Restaurant\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A settled restaurant bill was voided by a manager on its business date (ARCHITECTURE §4.5): Accounting
 * reverses its postings; Inventory gives back the ingredients when the food was not prepared.
 */
class RestaurantBillVoided implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $propertyId,
        public readonly int $outletId,
        public readonly int $billId,
        public readonly int $orderId,
        public readonly string $businessDate,
        public readonly string $grandTotal,
        public readonly bool $foodPrepared,
        public readonly string $reason,
    ) {}
}
