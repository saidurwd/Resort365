<?php

namespace Modules\Procurement\Actions;

use App\Support\Actions\Action;
use Modules\Core\Contracts\ApprovalEngine;
use Modules\Core\Enums\ApprovalStatus;
use Modules\Core\Exceptions\ApprovalRuleViolated;
use Modules\Procurement\Enums\PurchaseRequisitionStatus;
use Modules\Procurement\Exceptions\ProcurementRuleViolated;
use Modules\Procurement\Models\PurchaseRequisition;

/**
 * Withdraws a requisition that is waiting for approval or approved but not yet ordered (Step 5.5).
 */
class WithdrawPurchaseRequisition extends Action
{
    public function __construct(private readonly ApprovalEngine $approvals) {}

    /**
     * @throws ProcurementRuleViolated
     */
    public function handle(PurchaseRequisition $requisition, int $userId): PurchaseRequisition
    {
        return $this->transaction(function () use ($requisition, $userId): PurchaseRequisition {
            $locked = PurchaseRequisition::query()->lockForUpdate()->findOrFail($requisition->id);

            if (! in_array($locked->status, [PurchaseRequisitionStatus::Pending, PurchaseRequisitionStatus::Approved], true)) {
                throw new ProcurementRuleViolated(__('Requisition :no is :status.', ['no' => $locked->requisition_no, 'status' => mb_strtolower($locked->status->label())]));
            }

            if ($locked->status === PurchaseRequisitionStatus::Pending && $locked->approval_request_id !== null && $this->approvals->find($locked->approval_request_id)?->status === ApprovalStatus::Pending) {
                try {
                    $this->approvals->cancel($locked->approval_request_id, $userId);
                } catch (ApprovalRuleViolated $exception) {
                    throw new ProcurementRuleViolated($exception->getMessage(), $exception->getCode(), $exception);
                }
            }

            $locked->forceFill(['status' => PurchaseRequisitionStatus::Cancelled])->save();

            return $locked;
        });
    }
}
