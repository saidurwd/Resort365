<?php

namespace Modules\Inventory\Contracts;

use Modules\Inventory\DTOs\StockAdjustmentFact;
use Modules\Inventory\DTOs\StockIssueFact;
use Modules\Inventory\DTOs\StockOpeningFact;

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
     * A run of opening balances (consecutive opening movements of one store and date, starting at the movement given).
     */
    public function opening(int $batchId): ?StockOpeningFact;

    /**
     * What stock is worth at average cost: on the shelves per stock class, and in transit between stores (dispatched
     * transfers not yet received). Today when no date is given.
     *
     * @return array{stock: array<string, string>, in_transit: array<string, string>}
     */
    public function valuation(int $propertyId, ?string $asOf = null): array;

    /**
     * Ids of issues and the adjustment documents so far, for back-posting.
     *
     * @return array{issues: list<int>, adjustments: list<array{type: string, id: int}>, openings: list<int>}
     */
    public function history(): array;
}
