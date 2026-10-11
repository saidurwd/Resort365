<?php

namespace Modules\Inventory\Contracts;

use Modules\Inventory\DTOs\NewStockIssue;
use Modules\Inventory\DTOs\StockIssueSummary;
use Modules\Inventory\Exceptions\InsufficientStock;
use Modules\Inventory\Exceptions\StockRuleViolated;

/**
 * Issuing stock to a department (Step 5.4), for Housekeeping's amenity issues and later modules: one `ISS-…` document,
 * its lines issued at the average cost, all or nothing, and `StockIssued` after commit so Accounting charges the
 * department.
 */
interface StockIssuing
{
    /**
     * @throws InsufficientStock
     * @throws StockRuleViolated
     */
    public function issue(NewStockIssue $issue): StockIssueSummary;
}
