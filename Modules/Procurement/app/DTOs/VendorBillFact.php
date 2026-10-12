<?php

namespace Modules\Procurement\DTOs;

use App\Support\DTOs\Data;

/**
 * An approved vendor bill as the ledger needs it (Step 5.6). A goods bill clears `grni` (what was received, at the order
 * price) and books the price `variance` (billed net less `grni`, signed); a service bill books `expenseByClass`.
 */
final readonly class VendorBillFact extends Data
{
    /**
     * @param  array<string, string>  $expenseByClass  expense class => net amount (service bills)
     */
    public function __construct(
        public int $billId,
        public string $billNo,
        public int $propertyId,
        public int $vendorId,
        public ?int $departmentId,
        public string $date,
        public string $grni,
        public string $variance,
        public array $expenseByClass,
        public string $tax,
        public string $total,
    ) {}
}
