<?php

namespace Modules\Inventory\Contracts;

use Modules\Inventory\DTOs\StockAdjustmentFact;
use Modules\Inventory\DTOs\StockIssueFact;

/**
 * What Accounting reads from Inventory to post the ledger (Step 5.4). Facts only; Inventory never calls Accounting.
 */
interface StockFacts
{
    public function issue(int $issueId): ?StockIssueFact;

    /**
     * The adjustments of one document: a single adjustment movement (`stock_movement`) or a completed count (`stock_count`).
     */
    public function adjustment(string $sourceType, int $sourceId): ?StockAdjustmentFact;

    /**
     * Ids of issues and the adjustment documents so far, for back-posting.
     *
     * @return array{issues: list<int>, adjustments: list<array{type: string, id: int}>}
     */
    public function history(): array;
}
