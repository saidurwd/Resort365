<?php

namespace Modules\Procurement\DTOs;

use App\Support\DTOs\Data;

/**
 * A goods receipt as the ledger needs it (Step 5.6): the value received into stock per stock class.
 */
final readonly class ReceiptFact extends Data
{
    /**
     * @param  array<string, string>  $byClass  stock class => value at the order price
     */
    public function __construct(
        public int $receiptId,
        public string $receiptNo,
        public int $propertyId,
        public int $vendorId,
        public string $date,
        public array $byClass,
    ) {}
}
