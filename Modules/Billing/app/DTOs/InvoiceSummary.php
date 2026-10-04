<?php

namespace Modules\Billing\DTOs;

use App\Support\DTOs\Data;

/**
 * An issued invoice: its number and amounts (decimal strings). onAccount went to the city ledger.
 */
final readonly class InvoiceSummary extends Data
{
    public function __construct(
        public int $id,
        public string $invoiceNo,
        public int $folioId,
        public string $total,
        public string $paid,
        public string $onAccount,
    ) {}
}
