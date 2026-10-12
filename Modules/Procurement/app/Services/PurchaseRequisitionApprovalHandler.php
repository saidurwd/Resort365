<?php

namespace Modules\Procurement\Services;

use Modules\Core\Contracts\ApprovalHandler;
use Modules\Core\DTOs\ApprovalDecision;
use Modules\Procurement\Enums\PurchaseRequisitionStatus;
use Modules\Procurement\Models\PurchaseRequisition;

/**
 * Moves a purchase requisition on when its approval is decided (Step 5.5).
 */
class PurchaseRequisitionApprovalHandler implements ApprovalHandler
{
    public function approved(ApprovalDecision $decision): void
    {
        PurchaseRequisition::query()->whereKey($decision->payload['requisition_id'] ?? 0)->where('status', PurchaseRequisitionStatus::Pending->value)
            ->update(['status' => PurchaseRequisitionStatus::Approved->value, 'approved_at' => now()]);
    }

    public function rejected(ApprovalDecision $decision): void
    {
        PurchaseRequisition::query()->whereKey($decision->payload['requisition_id'] ?? 0)->where('status', PurchaseRequisitionStatus::Pending->value)
            ->update(['status' => PurchaseRequisitionStatus::Rejected->value]);
    }
}
