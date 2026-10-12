<?php

namespace Modules\Procurement\Actions;

use App\Support\Actions\Action;
use Modules\Core\Contracts\ApprovalEngine;
use Modules\Core\Enums\ApprovalStatus;
use Modules\Core\Exceptions\ApprovalRuleViolated;
use Modules\Procurement\Enums\VendorBillStatus;
use Modules\Procurement\Exceptions\ProcurementRuleViolated;
use Modules\Procurement\Models\VendorBill;

/**
 * Cancels a bill that is not yet approved (Step 5.6). An approved bill is in the ledger: correct it with a purchase
 * return or a credit from the vendor.
 */
class CancelVendorBill extends Action
{
    public function __construct(private readonly ApprovalEngine $approvals) {}

    /**
     * @throws ProcurementRuleViolated
     */
    public function handle(VendorBill $bill, int $userId): VendorBill
    {
        return $this->transaction(function () use ($bill, $userId): VendorBill {
            $locked = VendorBill::query()->lockForUpdate()->findOrFail($bill->id);

            if (! in_array($locked->status, [VendorBillStatus::Draft, VendorBillStatus::Pending], true)) {
                throw new ProcurementRuleViolated(__('Bill :no is :status: only a bill that is not yet approved can be cancelled.', ['no' => $locked->bill_no, 'status' => mb_strtolower($locked->status->label())]));
            }

            if ($locked->status === VendorBillStatus::Pending && $locked->approval_request_id !== null && $this->approvals->find($locked->approval_request_id)?->status === ApprovalStatus::Pending) {
                try {
                    $this->approvals->cancel($locked->approval_request_id, $userId);
                } catch (ApprovalRuleViolated $exception) {
                    throw new ProcurementRuleViolated($exception->getMessage(), $exception->getCode(), $exception);
                }
            }

            $locked->forceFill(['status' => VendorBillStatus::Cancelled, 'cancelled_at' => now()])->save();

            return $locked;
        });
    }
}
