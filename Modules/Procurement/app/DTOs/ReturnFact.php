<?php

namespace Modules\Procurement\DTOs;

use App\Support\DTOs\Data;

/**
 * A purchase return as the ledger needs it (Step 5.6): `grni` reverses goods not yet invoiced, `payable` (billed value and
 * its tax) reduces what is owed, `inventoryByClass` is what left stock, and `variance` the value credited by the vendor
 * less what left stock (signed).
 */
final readonly class ReturnFact extends Data
{
    /**
     * @param  array<string, string>  $inventoryByClass  stock class => cost that left stock
     */
    public function __construct(
        public int $returnId,
        public string $debitNoteNo,
        public int $propertyId,
        public int $vendorId,
        public string $date,
        public string $grni,
        public string $billedNet,
        public string $tax,
        public array $inventoryByClass,
        public string $variance,
    ) {}
}
