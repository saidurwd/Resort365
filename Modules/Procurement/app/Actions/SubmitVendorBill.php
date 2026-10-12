<?php

namespace Modules\Procurement\Actions;

use App\Support\Actions\Action;
use Modules\Core\Contracts\ApprovalEngine;
use Modules\Core\DTOs\ApprovalSummary;
use Modules\Procurement\DTOs\NewVendorBill;
use Modules\Procurement\Enums\VendorBillKind;
use Modules\Procurement\Enums\VendorBillStatus;
use Modules\Procurement\Exceptions\ProcurementRuleViolated;
use Modules\Procurement\Models\VendorBill;
use Modules\Procurement\Services\VendorBillApprover;

/**
 * Sends a draft vendor bill for approval (Step 5.6). A goods bill is matched again first, because other bills may have
 * used up the received quantity since it was written. A bill below every step of the `vendor_bill` workflow is approved
 * at once.
 */
class SubmitVendorBill extends Action
{
    public function __construct(
        private readonly ApprovalEngine $approvals,
        private readonly VendorBillApprover $approver,
        private readonly SaveVendorBill $save,
    ) {}

    /**
     * @throws ProcurementRuleViolated
     */
    public function handle(VendorBill $bill, int $userId): VendorBill
    {
        return $this->transaction(function () use ($bill, $userId): VendorBill {
            $locked = VendorBill::query()->lockForUpdate()->with('lines')->findOrFail($bill->id);

            if ($locked->status !== VendorBillStatus::Draft) {
                throw new ProcurementRuleViolated(__('Bill :no is :status.', ['no' => $locked->bill_no, 'status' => mb_strtolower($locked->status->label())]));
            }

            if ($locked->kind === VendorBillKind::Purchase) {
                $lines = $locked->lines->map(fn ($line): array => ['purchase_order_line_id' => $line->purchase_order_line_id, 'quantity' => (string) $line->quantity, 'unit_price' => (string) $line->unit_price])->all();
                $this->save->handle($locked, new NewVendorBill($locked->property_id, $locked->vendor_id, $locked->kind, $lines, $userId, $locked->purchase_order_id,
                    $locked->bill_date->toDateString(), $locked->due_on->toDateString(), $locked->vendor_reference, $locked->department_id, $locked->notes, $locked->match_overridden));
                $locked->refresh();
            }

            $approval = $this->approvals->submit(
                'vendor_bill', (string) $locked->total, __('Vendor bill :no', ['no' => $locked->bill_no]), $userId,
                ['bill_id' => $locked->id], $locked->property_id, 'vendor_bill', $locked->id,
            );

            if ($approval instanceof ApprovalSummary) {
                $locked->forceFill(['status' => VendorBillStatus::Pending, 'approval_request_id' => $approval->id])->save();
            } else {
                $locked->forceFill(['status' => VendorBillStatus::Pending])->save();
                $this->approver->approve($locked);
            }

            return $locked;
        });
    }
}
