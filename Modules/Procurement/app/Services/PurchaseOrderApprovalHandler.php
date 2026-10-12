<?php

namespace Modules\Procurement\Services;

use Modules\Core\Contracts\ApprovalHandler;
use Modules\Core\DTOs\ApprovalDecision;
use Modules\Procurement\Enums\PurchaseOrderStatus;
use Modules\Procurement\Models\PurchaseOrder;

/**
 * Moves a purchase order on when its approval is decided (Step 5.5). A rejected order goes back to a draft, to be
 * changed and sent for approval again.
 */
class PurchaseOrderApprovalHandler implements ApprovalHandler
{
    public function approved(ApprovalDecision $decision): void
    {
        PurchaseOrder::query()->whereKey($decision->payload['order_id'] ?? 0)->where('status', PurchaseOrderStatus::Pending->value)
            ->update(['status' => PurchaseOrderStatus::Approved->value, 'approved_at' => now()]);
    }

    public function rejected(ApprovalDecision $decision): void
    {
        PurchaseOrder::query()->whereKey($decision->payload['order_id'] ?? 0)->where('status', PurchaseOrderStatus::Pending->value)
            ->update(['status' => PurchaseOrderStatus::Draft->value]);
    }
}
