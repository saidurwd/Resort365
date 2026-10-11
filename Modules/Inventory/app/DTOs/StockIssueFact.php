<?php

namespace Modules\Inventory\DTOs;

use App\Support\DTOs\Data;

/**
 * A stock issue as the ledger needs it (Step 5.4): the value issued per stock class and the department charged.
 */
final readonly class StockIssueFact extends Data
{
    /**
     * @param  array<string, string>  $byClass  stock class => value at cost
     */
    public function __construct(
        public int $issueId,
        public string $issueNo,
        public int $propertyId,
        public ?int $departmentId,
        public string $date,
        public array $byClass,
    ) {}
}
