<?php

namespace Modules\Procurement\Actions;

use App\Support\Actions\Action;
use Modules\Core\Contracts\ApprovalEngine;
use Modules\Core\Enums\ApprovalStatus;
use Modules\Core\Exceptions\ApprovalRuleViolated;
use Modules\Procurement\Enums\PurchaseOrderStatus;
use Modules\Procurement\Exceptions\ProcurementRuleViolated;
use Modules\Procurement\Models\PurchaseOrder;

/**
 * Cancels an order that nothing has been received against (Step 5.5).
 */
class CancelPurchaseOrder extends Action
{
    public function __construct(private readonly ApprovalEngine $approvals) {}

    /**
     * @throws ProcurementRuleViolated
     */
    public function handle(PurchaseOrder $order, int $userId): PurchaseOrder
    {
        return $this->transaction(function () use ($order, $userId): PurchaseOrder {
            $locked = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);

            if (! in_array($locked->status, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Pending, PurchaseOrderStatus::Approved, PurchaseOrderStatus::Sent], true)) {
                throw new ProcurementRuleViolated(__('Order :no is :status.', ['no' => $locked->order_no, 'status' => mb_strtolower($locked->status->label())]));
            }

            if ($locked->status === PurchaseOrderStatus::Pending && $locked->approval_request_id !== null && $this->approvals->find($locked->approval_request_id)?->status === ApprovalStatus::Pending) {
                try {
                    $this->approvals->cancel($locked->approval_request_id, $userId);
                } catch (ApprovalRuleViolated $exception) {
                    throw new ProcurementRuleViolated($exception->getMessage(), $exception->getCode(), $exception);
                }
            }

            $locked->forceFill(['status' => PurchaseOrderStatus::Cancelled, 'cancelled_at' => now()])->save();

            return $locked;
        });
    }
}
