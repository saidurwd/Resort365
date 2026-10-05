<?php

namespace Modules\Billing\Contracts;

use Modules\Billing\DTOs\CityLedgerAccount;
use Modules\Billing\DTOs\CityLedgerCharge;
use Modules\Billing\Exceptions\ChargeRejected;

/**
 * Billing a company's account (city ledger, ARCHITECTURE §5.9) from another module (Step 3.7: a restaurant
 * bill settled "to city ledger"). Call inside the transaction that settles the source.
 */
interface CityLedgerAccounts
{
    /**
     * Active companies matching a name (at most 20), with what they owe.
     *
     * @return list<CityLedgerAccount>
     */
    public function accounts(string $term): array;

    public function account(int $companyId): ?CityLedgerAccount;

    /**
     * @return int the city-ledger entry
     *
     * @throws ChargeRejected when the company is inactive or would go over its credit limit
     */
    public function charge(CityLedgerCharge $charge): int;

    /**
     * Takes back an entry while nothing is paid or credited on it (its source was voided).
     *
     * @throws ChargeRejected when money was already received against it
     */
    public function cancel(int $entryId, string $reason): void;
}
