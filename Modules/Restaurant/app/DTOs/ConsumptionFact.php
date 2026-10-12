<?php

namespace Modules\Restaurant\DTOs;

use App\Support\DTOs\Data;

/**
 * What a settled bill consumed as the ledger needs it (Step 5.7): the cost of the ingredients per stock class, and whether
 * the bill was complimentary (then the cost is staff-meal or entertainment expense, not cost of sales).
 */
final readonly class ConsumptionFact extends Data
{
    /**
     * @param  array<string, string>  $byClass  stock class => cost
     */
    public function __construct(
        public int $billId,
        public string $billNo,
        public int $propertyId,
        public int $outletId,
        public string $outletName,
        public string $businessDate,
        public bool $complimentary,
        public array $byClass,
    ) {}
}
