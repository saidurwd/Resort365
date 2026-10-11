<?php

namespace Modules\Inventory\DTOs;

use App\Support\DTOs\Data;

/**
 * Stock adjustments of one document as the ledger needs them (Step 5.4): the net value per stock class, a gain positive
 * and a loss negative.
 */
final readonly class StockAdjustmentFact extends Data
{
    /**
     * @param  array<string, string>  $byClass  stock class => signed value at cost
     */
    public function __construct(
        public string $sourceType,
        public int $sourceId,
        public int $propertyId,
        public string $reference,
        public string $date,
        public array $byClass,
    ) {}
}
