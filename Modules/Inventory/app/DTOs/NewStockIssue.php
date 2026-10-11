<?php

namespace Modules\Inventory\DTOs;

use App\Support\DTOs\Data;

/**
 * Stock to issue from a store to a department (Step 5.4). Every line goes at the store's average cost, or none does.
 */
final readonly class NewStockIssue extends Data
{
    /**
     * @param  list<array{item_id: int, quantity: string}>  $lines  quantities in each item's base unit
     */
    public function __construct(
        public int $storeId,
        public ?int $departmentId,
        public array $lines,
        public ?string $reason = null,
        public ?string $notes = null,
        public ?string $sourceType = null,
        public ?int $sourceId = null,
        public ?int $requisitionId = null,
        public ?int $userId = null,
    ) {}
}
