<?php

namespace Modules\Procurement\Services;

use Modules\Procurement\Enums\VendorBillStatus;
use Modules\Procurement\Events\VendorBillApproved;
use Modules\Procurement\Models\PurchaseOrder;
use Modules\Procurement\Models\VendorBill;

/**
 * Makes a bill payable (Step 5.6): marks it approved, tells the ledger, and lets its order close once everything
 * received has been billed.
 */
class VendorBillApprover
{
    public function __construct(private readonly OrderProgress $progress) {}

    public function approve(VendorBill $bill): VendorBill
    {
        $bill->forceFill(['status' => VendorBillStatus::Approved, 'approved_at' => now()])->save();

        $order = $bill->purchase_order_id !== null ? PurchaseOrder::query()->find($bill->purchase_order_id) : null;

        if ($order instanceof PurchaseOrder) {
            $this->progress->refresh($order);
        }

        VendorBillApproved::dispatch($bill->tenant_id, $bill->property_id, $bill->id);

        return $bill;
    }
}
