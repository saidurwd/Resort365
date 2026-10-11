<?php

namespace Modules\Inventory\Actions;

use App\Support\Actions\Action;
use Modules\Inventory\Contracts\StockIssuing;
use Modules\Inventory\DTOs\NewStockIssue;
use Modules\Inventory\DTOs\StockIssueSummary;
use Modules\Inventory\Exceptions\StockRuleViolated;

/**
 * A direct issue without a requisition (Step 5.4): the store keeper puts items out to a department, with a reason.
 */
class IssueStock extends Action
{
    public function __construct(private readonly StockIssuing $issuing) {}

    /**
     * @param  list<array{item_id: int, quantity: string}>  $lines
     *
     * @throws StockRuleViolated
     */
    public function handle(int $storeId, int $departmentId, array $lines, string $reason, ?string $notes = null, ?int $userId = null): StockIssueSummary
    {
        if (trim($reason) === '') {
            throw new StockRuleViolated(__('Give a reason for the issue.'));
        }

        return $this->issuing->issue(new NewStockIssue($storeId, $departmentId, $lines, mb_substr(trim($reason), 0, 60), $notes, 'direct', null, null, $userId));
    }
}
