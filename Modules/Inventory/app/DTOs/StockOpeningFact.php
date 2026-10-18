<?php

namespace Modules\Inventory\DTOs;

use App\Support\DTOs\Data;

/**
 * A run of opening balances as the ledger needs it (Step 5.8): the value per stock class.
 */
final readonly class StockOpeningFact extends Data
{
    /**
     * @param  array<string, string>  $byClass  stock class => value at cost
     */
    public function __construct(
        public int $batchId,
        public int $propertyId,
        public string $date,
        public array $byClass,
    ) {}
}
