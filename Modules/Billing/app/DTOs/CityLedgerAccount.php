<?php

namespace Modules\Billing\DTOs;

use App\Support\DTOs\Data;

/**
 * A company that can be billed on account (CityLedgerAccounts::accounts): what it owes and the credit
 * it has left (null = no limit).
 */
final readonly class CityLedgerAccount extends Data
{
    public function __construct(
        public int $companyId,
        public string $name,
        public string $owed,
        public ?string $creditLeft,
    ) {}
}
