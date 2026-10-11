<?php

namespace Modules\Inventory\Services;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\Inventory\Contracts\StockIssuing;
use Modules\Inventory\Contracts\StockMovementContract;
use Modules\Inventory\DTOs\NewStockIssue;
use Modules\Inventory\DTOs\NewStockMovement;
use Modules\Inventory\DTOs\StockIssueSummary;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Events\StockIssued;
use Modules\Inventory\Exceptions\StockRuleViolated;
use Modules\Inventory\Models\StockIssue;
use Modules\Inventory\Models\StockIssueLine;
use Modules\Inventory\Models\Store;
use Modules\Property\Contracts\DepartmentDirectory;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Property\DTOs\DepartmentSummary;

/**
 * Issues stock to a department (Step 5.4): one document, one `issue` movement per line at the store's average cost
 * (carrying the department), in one transaction, then `StockIssued`.
 */
class StockIssuer implements StockIssuing
{
    public function __construct(
        private readonly StockMovementContract $stock,
        private readonly DocumentNumbers $numbers,
        private readonly DepartmentDirectory $departments,
        private readonly PropertyDirectory $properties,
    ) {}

    public function issue(NewStockIssue $issue): StockIssueSummary
    {
        $store = Store::query()->find($issue->storeId) ?? throw new StockRuleViolated(__('Unknown store.'));

        if ($issue->departmentId !== null && ! $this->departments->find($issue->departmentId) instanceof DepartmentSummary) {
            throw new StockRuleViolated(__('Choose one of the tenant\'s departments.'));
        }

        if ($issue->lines === []) {
            throw new StockRuleViolated(__('Add at least one item.'));
        }

        if (count(array_unique(array_column($issue->lines, 'item_id'))) !== count($issue->lines)) {
            throw new StockRuleViolated(__('An item is listed once per issue.'));
        }

        foreach ($issue->lines as $line) {
            if (! BigDecimal::of($line['quantity'])->isPositive()) {
                throw new StockRuleViolated(__('Every quantity must be more than zero.'));
            }
        }

        $date = $this->properties->find($store->property_id)->businessDate ?? now()->toDateString();

        return DB::transaction(function () use ($issue, $store, $date): StockIssueSummary {
            $document = StockIssue::query()->create([
                'property_id' => $store->property_id, 'issue_no' => $this->numbers->next('stock_issue'), 'store_id' => $store->id, 'department_id' => $issue->departmentId,
                'requisition_id' => $issue->requisitionId, 'source_type' => $issue->sourceType, 'source_id' => $issue->sourceId, 'reason' => $issue->reason, 'notes' => $issue->notes,
                'issued_on' => $date, 'issued_by' => $issue->userId,
            ]);
            $total = BigDecimal::zero();

            foreach ($issue->lines as $line) {
                $movement = $this->stock->record(new NewStockMovement(
                    MovementType::Issue, $store->id, $line['item_id'], $line['quantity'], date: $date, reason: $issue->reason, notes: $document->issue_no, sourceType: 'stock_issue',
                    sourceId: $document->id, sourceEvent: 'issued', idempotencyKey: 'issue:'.$document->id.':'.$line['item_id'], userId: $issue->userId, departmentId: $issue->departmentId,
                ));
                StockIssueLine::query()->create([
                    'property_id' => $store->property_id, 'stock_issue_id' => $document->id, 'item_id' => $line['item_id'], 'quantity' => ltrim($movement->quantity, '-'),
                    'unit_cost' => $movement->unitCost, 'total_cost' => $movement->totalCost, 'stock_movement_id' => $movement->id,
                ]);
                $total = $total->plus($movement->totalCost);
            }

            StockIssued::dispatch($store->tenant_id, $store->property_id, $document->id);

            return new StockIssueSummary($document->id, $document->issue_no, $store->id, $issue->departmentId, (string) $total->toScale(2), count($issue->lines));
        }, 3);
    }
}
