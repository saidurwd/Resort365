<?php

namespace Modules\Core\DTOs;

use App\Support\DTOs\Data;
use Carbon\CarbonImmutable;

/**
 * One of the platform's invoices to the tenant (Step 7.4). Amounts are decimal strings.
 */
final readonly class SubscriptionInvoiceSummary extends Data
{
    public function __construct(
        public int $id,
        public string $number,
        public string $description,
        public CarbonImmutable $issuedOn,
        public CarbonImmutable $dueOn,
        public string $currencyCode,
        public string $amount,
        public string $balance,
        public string $statusLabel,
        public string $statusColor,
        public bool $overdue,
    ) {}
}
