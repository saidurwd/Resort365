<?php

namespace Modules\Billing\DTOs;

use App\Support\DTOs\Data;

/**
 * A folio balance moved to a company's city ledger account (Accounting, Step 4.2).
 */
final readonly class CityLedgerTransferFact extends Data
{
    public function __construct(
        public int $entryId,
        public int $propertyId,
        public int $companyId,
        public int $folioId,
        public string $date,
        public string $amount,
        public string $description,
    ) {}
}
