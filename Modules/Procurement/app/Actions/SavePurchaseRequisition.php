<?php

namespace Modules\Procurement\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Core\Contracts\ApprovalEngine;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\Core\DTOs\ApprovalSummary;
use Modules\Inventory\Contracts\ItemCatalog;
use Modules\Procurement\Enums\PurchaseRequisitionStatus;
use Modules\Procurement\Enums\RequisitionSource;
use Modules\Procurement\Exceptions\ProcurementRuleViolated;
use Modules\Procurement\Models\PurchaseRequisition;
use Modules\Procurement\Models\PurchaseRequisitionLine;
use Modules\Property\Contracts\DepartmentDirectory;
use Modules\Property\DTOs\DepartmentSummary;

/**
 * A department asks to buy items (Step 5.5). The lines are valued at the cost given, else the item's standard cost, and
 * the total goes to the `purchase_requisition` approval workflow: a requisition below every step is approved at once.
 */
class SavePurchaseRequisition extends Action
{
    public function __construct(
        private readonly DocumentNumbers $numbers,
        private readonly ApprovalEngine $approvals,
        private readonly ItemCatalog $items,
        private readonly DepartmentDirectory $departments,
    ) {}

    /**
     * @param  list<array{item_id: int, quantity: string, estimated_unit_cost?: string|null, notes?: string|null}>  $lines
     *
     * @throws ProcurementRuleViolated
     */
    public function handle(int $propertyId, ?int $departmentId, array $lines, int $userId, ?string $neededOn = null, ?string $notes = null, RequisitionSource $source = RequisitionSource::Manual): PurchaseRequisition
    {
        if ($lines === []) {
            throw new ProcurementRuleViolated(__('Add at least one item.'));
        }

        if (count(array_unique(array_column($lines, 'item_id'))) !== count($lines)) {
            throw new ProcurementRuleViolated(__('An item is listed once per requisition.'));
        }

        if ($departmentId !== null && ! $this->departments->find($departmentId) instanceof DepartmentSummary) {
            throw new ProcurementRuleViolated(__('Choose a department of this company.'));
        }

        $value = BigDecimal::zero();
        $rows = [];

        foreach ($lines as $line) {
            $item = $this->items->find((int) $line['item_id']) ?? throw new ProcurementRuleViolated(__('Unknown item.'));

            if (! BigDecimal::of($line['quantity'])->isPositive()) {
                throw new ProcurementRuleViolated(__(':item needs a quantity above zero.', ['item' => $item->name]));
            }

            $cost = ($line['estimated_unit_cost'] ?? null) ?: ($item->standardCost ?? '0');
            $value = $value->plus(BigDecimal::of($line['quantity'])->multipliedBy($cost));
            $rows[] = ['item_id' => $item->id, 'quantity' => $line['quantity'], 'estimated_unit_cost' => $cost, 'notes' => $line['notes'] ?? null];
        }

        $value = $value->toScale(2, RoundingMode::HalfUp);

        return $this->transaction(function () use ($propertyId, $departmentId, $rows, $userId, $neededOn, $notes, $source, $value): PurchaseRequisition {
            $requisition = PurchaseRequisition::query()->create([
                'property_id' => $propertyId, 'requisition_no' => $this->numbers->next('purchase_requisition'), 'department_id' => $departmentId, 'needed_on' => $neededOn,
                'status' => PurchaseRequisitionStatus::Pending, 'source' => $source, 'notes' => $notes, 'requested_by' => $userId, 'estimated_value' => (string) $value,
            ]);

            foreach ($rows as $row) {
                PurchaseRequisitionLine::query()->create(['property_id' => $propertyId, 'purchase_requisition_id' => $requisition->id, ...$row]);
            }

            $approval = $this->approvals->submit(
                'purchase_requisition', (string) $value, __('Purchase requisition :no', ['no' => $requisition->requisition_no]), $userId,
                ['requisition_id' => $requisition->id], $propertyId, 'purchase_requisition', $requisition->id,
            );

            if (! $approval instanceof ApprovalSummary) {
                $requisition->forceFill(['status' => PurchaseRequisitionStatus::Approved, 'approved_at' => now()])->save();
            } else {
                $requisition->forceFill(['approval_request_id' => $approval->id])->save();
            }

            return $requisition->load('lines');
        }, attempts: 3);
    }
}
