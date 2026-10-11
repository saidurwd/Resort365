<?php

namespace Modules\Inventory\Actions;

use App\Support\Actions\Action;
use Modules\Core\Contracts\ApprovalEngine;
use Modules\Core\Enums\ApprovalStatus;
use Modules\Core\Exceptions\ApprovalRuleViolated;
use Modules\Inventory\Enums\RequisitionStatus;
use Modules\Inventory\Exceptions\StockRuleViolated;
use Modules\Inventory\Models\StockRequisition;

/**
 * The requester takes back a requisition that is not issued yet (Step 5.4); a request waiting for approval is withdrawn too.
 */
class WithdrawRequisition extends Action
{
    public function __construct(private readonly ApprovalEngine $approvals) {}

    /**
     * @throws StockRuleViolated
     */
    public function handle(StockRequisition $requisition, int $userId): StockRequisition
    {
        return $this->transaction(function () use ($requisition, $userId): StockRequisition {
            $locked = StockRequisition::query()->lockForUpdate()->findOrFail($requisition->id);

            if (! in_array($locked->status, [RequisitionStatus::Pending, RequisitionStatus::Approved], true)) {
                throw new StockRuleViolated(__('Requisition :no is :status.', ['no' => $locked->requisition_no, 'status' => mb_strtolower($locked->status->label())]));
            }

            if ($locked->status === RequisitionStatus::Pending && $locked->approval_request_id !== null && $this->approvals->find($locked->approval_request_id)?->status === ApprovalStatus::Pending) {
                try {
                    $this->approvals->cancel($locked->approval_request_id, $userId);
                } catch (ApprovalRuleViolated $exception) {
                    throw new StockRuleViolated($exception->getMessage(), $exception->getCode(), $exception);
                }
            }

            $locked->forceFill(['status' => RequisitionStatus::Cancelled])->save();

            return $locked;
        });
    }
}
