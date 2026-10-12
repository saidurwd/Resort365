<?php

namespace Modules\Procurement\DTOs;

use App\Support\DTOs\Data;

/**
 * A payment to a vendor as the ledger needs it (Step 5.6): the whole amount, allocated or advance.
 */
final readonly class VendorPaymentFact extends Data
{
    public function __construct(
        public int $paymentId,
        public string $paymentNo,
        public int $propertyId,
        public int $vendorId,
        public string $date,
        public string $method,
        public string $amount,
    ) {}
}
