<?php

namespace Modules\Guest\DTOs;

use App\Support\DTOs\Data;

/**
 * A company as other modules see it (GuestLookup). credit_limit is a decimal string in the tenant's base currency.
 */
final readonly class CompanySummary extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $taxNumber,
        public string $creditLimit,
        public int $paymentTermsDays,
        public bool $isActive,
    ) {}
}
