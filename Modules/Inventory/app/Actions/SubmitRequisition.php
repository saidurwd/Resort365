<?php

namespace Modules\Inventory\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Core\Contracts\ApprovalEngine;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\Core\DTOs\ApprovalSummary;
use Modules\Inventory\Enums\RequisitionStatus;
use Modules\Inventory\Exceptions\StockRuleViolated;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\StockBalance;
use Modules\Inventory\Models\StockRequisition;
use Modules\Inventory\Models\StockRequisitionLine;
use Modules\Inventory\Models\Store;
use Modules\Property\Contracts\DepartmentDirectory;

/**
 * A department asks a store for items (Step 5.4). The lines are valued at the store's average cost (else the item's
 * standard cost) and that value goes to the `store_requisition` approval workflow: a requisition below every step is
 * approved at once, otherwise it waits for the approvers.
 */
class SubmitRequisition extends Action
{
    public function __construct(
        private readonly DocumentNumbers $numbers,
        private readonly ApprovalEngine $approvals,
        private readonly DepartmentDirectory $departments,
    ) {}

    /**
     * @param  list<array{item_id: int, quantity: string}>  $lines
     *
     * @throws StockRuleViolated
     */
    public function handle(int $storeId, int $departmentId, array $lines, int $userId, ?string $neededOn = null, ?string $notes = null): StockRequisition
    {
        $store = Store::query()->find($storeId) ?? throw new StockRuleViolated(__('Choose the store to ask.'));
        $department = $this->departments->find($departmentId) ?? throw new StockRuleViolated(__('Choose the department asking.'));

        if ($lines === []) {
            throw new StockRuleViolated(__('Add at least one item.'));
        }

        if (count(array_unique(array_column($lines, 'item_id'))) !== count($lines)) {
            throw new StockRuleViolated(__('An item is listed once per requisition.'));
        }

        $value = BigDecimal::zero();

        foreach ($lines as $line) {
            $item = Item::query()->find($line['item_id']) ?? throw new StockRuleViolated(__('Unknown item.'));

            if (! $item->is_stockable || ! BigDecimal::of($line['quantity'])->isPositive()) {
                throw new StockRuleViolated(__(':item must be a stocked item with a quantity above zero.', ['item' => $item->name]));
            }

            $avg = StockBalance::query()->where('store_id', $store->id)->where('item_id', $item->id)->value('avg_cost');
            $cost = BigDecimal::of($avg ?? '0')->isPositive() ? (string) $avg : (string) ($item->standard_cost ?? '0');
            $value = $value->plus(BigDecimal::of($line['quantity'])->multipliedBy($cost));
        }

        $value = $value->toScale(2, RoundingMode::HalfUp);

        return $this->transaction(function () use ($store, $department, $lines, $userId, $neededOn, $notes, $value): StockRequisition {
            $requisition = StockRequisition::query()->create([
                'property_id' => $store->property_id, 'requisition_no' => $this->numbers->next('store_requisition'), 'store_id' => $store->id, 'department_id' => $department->id,
                'needed_on' => $neededOn, 'status' => RequisitionStatus::Pending, 'notes' => $notes, 'requested_by' => $userId, 'estimated_value' => (string) $value,
            ]);

            foreach ($lines as $line) {
                StockRequisitionLine::query()->create(['property_id' => $store->property_id, 'stock_requisition_id' => $requisition->id, 'item_id' => $line['item_id'], 'quantity' => $line['quantity']]);
            }

            $approval = $this->approvals->submit(
                'store_requisition', (string) $value, __('Requisition :no for :department', ['no' => $requisition->requisition_no, 'department' => $department->name]), $userId,
                ['requisition_id' => $requisition->id], $store->property_id, 'store_requisition', $requisition->id,
            );

            if (! $approval instanceof ApprovalSummary) {
                $requisition->forceFill(['status' => RequisitionStatus::Approved, 'approved_at' => now()])->save();
            } else {
                $requisition->forceFill(['approval_request_id' => $approval->id])->save();
            }

            return $requisition->load('lines');
        }, attempts: 3);
    }
}
