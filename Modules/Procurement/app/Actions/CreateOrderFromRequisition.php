<?php

namespace Modules\Procurement\Actions;

use App\Support\Actions\Action;
use Carbon\CarbonImmutable;
use Modules\Procurement\DTOs\NewPurchaseOrder;
use Modules\Procurement\Enums\PurchaseRequisitionStatus;
use Modules\Procurement\Exceptions\ProcurementRuleViolated;
use Modules\Procurement\Models\PurchaseOrder;
use Modules\Procurement\Models\PurchaseRequisition;
use Modules\Procurement\Services\VendorPrices;

/**
 * Orders an approved requisition straight from one vendor (Step 5.5): each line is priced from the vendor's price list
 * for the quantity, else at the requisition's estimate. The order is a draft to review, price and submit.
 */
class CreateOrderFromRequisition extends Action
{
    public function __construct(
        private readonly SavePurchaseOrder $save,
        private readonly VendorPrices $prices,
    ) {}

    /**
     * @throws ProcurementRuleViolated
     */
    public function handle(PurchaseRequisition $requisition, int $vendorId, int $storeId, int $userId, ?string $expectedOn = null): PurchaseOrder
    {
        if ($requisition->status !== PurchaseRequisitionStatus::Approved) {
            throw new ProcurementRuleViolated(__('Requisition :no is :status: only an approved requisition can be ordered.', ['no' => $requisition->requisition_no, 'status' => mb_strtolower($requisition->status->label())]));
        }

        $today = CarbonImmutable::now();
        $lines = [];

        foreach ($requisition->lines as $line) {
            $price = $this->prices->price($vendorId, $line->item_id, $today, (string) $line->quantity) ?? (string) $line->estimated_unit_cost;
            $lines[] = ['item_id' => $line->item_id, 'quantity' => (string) $line->quantity, 'unit_price' => $price];
        }

        return $this->save->handle(null, new NewPurchaseOrder($requisition->property_id, $vendorId, $storeId, $lines, $userId, $requisition->department_id, null, $expectedOn, $requisition->notes, $requisition->id));
    }
}
