<?php

namespace Modules\Procurement\Services;

use Modules\Core\Contracts\ApprovalHandler;
use Modules\Core\DTOs\ApprovalDecision;
use Modules\Procurement\Enums\VendorBillStatus;
use Modules\Procurement\Models\VendorBill;

/**
 * Moves a vendor bill on when its approval is decided (Step 5.6). A rejected bill goes back to a draft.
 */
class VendorBillApprovalHandler implements ApprovalHandler
{
    public function __construct(private readonly VendorBillApprover $approver) {}

    public function approved(ApprovalDecision $decision): void
    {
        $bill = VendorBill::query()->whereKey($decision->payload['bill_id'] ?? 0)->where('status', VendorBillStatus::Pending->value)->first();

        if ($bill instanceof VendorBill) {
            $this->approver->approve($bill);
        }
    }

    public function rejected(ApprovalDecision $decision): void
    {
        VendorBill::query()->whereKey($decision->payload['bill_id'] ?? 0)->where('status', VendorBillStatus::Pending->value)->update(['status' => VendorBillStatus::Draft->value]);
    }
}
