<?php

namespace Modules\Procurement\Actions;

use App\Support\Actions\Action;
use Modules\Core\Contracts\ApprovalEngine;
use Modules\Core\DTOs\ApprovalSummary;
use Modules\Procurement\Enums\PurchaseOrderStatus;
use Modules\Procurement\Exceptions\ProcurementRuleViolated;
use Modules\Procurement\Models\PurchaseOrder;

/**
 * Sends a draft purchase order for approval (Step 5.5). The total decides which steps of the `purchase_order` workflow
 * apply; an order below every step is approved at once.
 */
class SubmitPurchaseOrder extends Action
{
    public function __construct(private readonly ApprovalEngine $approvals) {}

    /**
     * @throws ProcurementRuleViolated
     */
    public function handle(PurchaseOrder $order, int $userId): PurchaseOrder
    {
        return $this->transaction(function () use ($order, $userId): PurchaseOrder {
            $locked = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);

            if ($locked->status !== PurchaseOrderStatus::Draft) {
                throw new ProcurementRuleViolated(__('Order :no is :status.', ['no' => $locked->order_no, 'status' => mb_strtolower($locked->status->label())]));
            }

            $approval = $this->approvals->submit(
                'purchase_order', (string) $locked->total, __('Purchase order :no', ['no' => $locked->order_no]), $userId,
                ['order_id' => $locked->id], $locked->property_id, 'purchase_order', $locked->id,
            );

            if ($approval instanceof ApprovalSummary) {
                $locked->forceFill(['status' => PurchaseOrderStatus::Pending, 'approval_request_id' => $approval->id])->save();
            } else {
                $locked->forceFill(['status' => PurchaseOrderStatus::Approved, 'approved_at' => now()])->save();
            }

            return $locked;
        });
    }
}
