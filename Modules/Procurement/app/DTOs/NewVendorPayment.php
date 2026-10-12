<?php

namespace Modules\Procurement\DTOs;

use App\Support\DTOs\Data;
use Modules\Procurement\Enums\VendorPaymentMethod;

/**
 * A payment to a vendor (Step 5.6). `allocations` maps a bill id to the amount of the payment applied to it; what is
 * left over stays an advance.
 */
final readonly class NewVendorPayment extends Data
{
    /**
     * @param  array<int, string>  $allocations  bill id => amount
     */
    public function __construct(
        public int $propertyId,
        public int $vendorId,
        public VendorPaymentMethod $method,
        public string $amount,
        public int $userId,
        public array $allocations = [],
        public ?string $paidOn = null,
        public ?string $reference = null,
        public ?string $notes = null,
    ) {}
}
