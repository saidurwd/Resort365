<?php

namespace Modules\Inventory\Services;

use Modules\Core\Contracts\ApprovalHandler;
use Modules\Core\DTOs\ApprovalDecision;
use Modules\Inventory\Enums\RequisitionStatus;
use Modules\Inventory\Models\StockRequisition;

/**
 * Moves a requisition on when its approval is decided (Step 5.4).
 */
class RequisitionApprovalHandler implements ApprovalHandler
{
    public function approved(ApprovalDecision $decision): void
    {
        StockRequisition::query()->whereKey($decision->payload['requisition_id'] ?? 0)->where('status', RequisitionStatus::Pending->value)
            ->update(['status' => RequisitionStatus::Approved->value, 'approved_at' => now()]);
    }

    public function rejected(ApprovalDecision $decision): void
    {
        StockRequisition::query()->whereKey($decision->payload['requisition_id'] ?? 0)->where('status', RequisitionStatus::Pending->value)
            ->update(['status' => RequisitionStatus::Rejected->value]);
    }
}
