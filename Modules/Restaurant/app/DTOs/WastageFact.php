<?php

namespace Modules\Restaurant\DTOs;

use App\Support\DTOs\Data;

/**
 * A wastage entry as the ledger needs it (Step 5.7): the cost written off per stock class.
 */
final readonly class WastageFact extends Data
{
    /**
     * @param  array<string, string>  $byClass  stock class => cost
     */
    public function __construct(
        public int $wastageId,
        public string $wastageNo,
        public int $propertyId,
        public string $date,
        public array $byClass,
    ) {}
}
