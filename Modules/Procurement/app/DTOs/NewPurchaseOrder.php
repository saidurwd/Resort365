<?php

namespace Modules\Procurement\DTOs;

use App\Support\DTOs\Data;

/**
 * What it takes to write a purchase order (Step 5.5). Quantities are in each item's base unit; prices are per base unit.
 */
final readonly class NewPurchaseOrder extends Data
{
    /**
     * @param  list<array{item_id: int, quantity: string, unit_price: string, tax_category_id?: int|null}>  $lines
     */
    public function __construct(
        public int $propertyId,
        public int $vendorId,
        public int $storeId,
        public array $lines,
        public int $userId,
        public ?int $departmentId = null,
        public ?string $orderedOn = null,
        public ?string $expectedOn = null,
        public ?string $notes = null,
        public ?int $requisitionId = null,
        public ?int $quotationId = null,
        public ?int $paymentTermsDays = null,
    ) {}
}
