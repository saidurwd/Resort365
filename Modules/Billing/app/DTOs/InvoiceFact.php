<?php

namespace Modules\Billing\DTOs;

use App\Support\DTOs\Data;

/**
 * An issued invoice as the ledger needs it (Accounting, Step 4.2): `depositsApplied` is what the
 * booking's advance deposits cover of the folio, moved from Customer Advances to the guest ledger.
 */
final readonly class InvoiceFact extends Data
{
    public function __construct(
        public int $invoiceId,
        public int $propertyId,
        public string $invoiceNo,
        public string $issueDate,
        public int $folioId,
        public ?int $reservationId,
        public string $depositsApplied,
    ) {}
}
