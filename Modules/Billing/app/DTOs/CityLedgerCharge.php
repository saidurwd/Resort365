<?php

namespace Modules\Billing\DTOs;

use App\Support\DTOs\Data;

/**
 * Something another module bills to a company's account (CityLedgerAccounts::charge), e.g. a restaurant
 * bill: amount (decimal string), what it is, and what it points at.
 */
final readonly class CityLedgerCharge extends Data
{
    public function __construct(
        public int $propertyId,
        public int $companyId,
        public string $amount,
        public string $description,
        public string $referenceType,
        public int $referenceId,
    ) {}
}
