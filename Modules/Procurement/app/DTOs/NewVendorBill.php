<?php

namespace Modules\Procurement\DTOs;

use App\Support\DTOs\Data;
use Modules\Procurement\Enums\VendorBillKind;

/**
 * What it takes to enter a vendor bill (Step 5.6). A goods bill names an order and bills order lines
 * (`purchase_order_line_id`, `quantity`, `unit_price`); a service bill describes each line (`description`,
 * `expense_class`, `quantity`, `unit_price`, `tax_category_id`).
 */
final readonly class NewVendorBill extends Data
{
    /**
     * @param  list<array<string, mixed>>  $lines
     */
    public function __construct(
        public int $propertyId,
        public int $vendorId,
        public VendorBillKind $kind,
        public array $lines,
        public int $userId,
        public ?int $orderId = null,
        public ?string $billDate = null,
        public ?string $dueOn = null,
        public ?string $vendorReference = null,
        public ?int $departmentId = null,
        public ?string $notes = null,
        public bool $overrideMatch = false,
    ) {}
}
