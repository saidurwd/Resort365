<?php

namespace Modules\Restaurant\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A restaurant bill was paid in full (ARCHITECTURE §4.5): Accounting posts F&B revenue, taxes and payments
 * (Phase 4); Inventory deducts recipe ingredients (Phase 5); Reports count the sale. Amounts are decimal
 * strings; payments by method (tips included).
 */
class RestaurantBillSettled implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  array<string, string>  $payments  method => amount
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly int $propertyId,
        public readonly int $outletId,
        public readonly int $billId,
        public readonly int $orderId,
        public readonly string $businessDate,
        public readonly string $grandTotal,
        public readonly string $serviceCharge,
        public readonly string $taxTotal,
        public readonly string $tipTotal,
        public readonly bool $complimentary,
        public readonly array $payments,
    ) {}
}
