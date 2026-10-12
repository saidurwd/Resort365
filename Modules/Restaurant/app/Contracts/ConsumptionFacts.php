<?php

namespace Modules\Restaurant\Contracts;

use Modules\Restaurant\DTOs\ConsumptionFact;
use Modules\Restaurant\DTOs\WastageFact;

/**
 * What Accounting reads from Restaurant to book cost of sales and wastage (Step 5.7). Facts only.
 */
interface ConsumptionFacts
{
    public function consumption(int $billId): ?ConsumptionFact;

    public function wastage(int $wastageId): ?WastageFact;

    /**
     * Ids of bills with consumption and of wastage entries so far, for back-posting.
     *
     * @return array{bills: list<int>, wastage: list<int>}
     */
    public function history(): array;
}
