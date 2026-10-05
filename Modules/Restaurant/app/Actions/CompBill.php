<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Modules\Restaurant\Enums\BillStatus;
use Modules\Restaurant\Enums\CompReason;
use Modules\Restaurant\Enums\PaymentMethod;
use Modules\Restaurant\Exceptions\PosNotAllowed;
use Modules\Restaurant\Models\PosBill;
use Modules\Restaurant\Models\PosPayment;
use Modules\Restaurant\Models\PosSession;
use Modules\Restaurant\Services\BillSettlement;
use Modules\Restaurant\Services\ManagerApprovals;

/**
 * Makes a whole printed bill complimentary (ARCHITECTURE §5.10.7): a reason (a note for "other"), and
 * restaurant.bill.comp or a manager's PIN (`bill.comp`). Settled by a complimentary payment of the full
 * amount. TODO(step-5.x): value comps at recipe cost for the expense posting; until then at the sale value.
 */
class CompBill extends Action
{
    public const string APPROVAL = 'bill.comp';

    public function __construct(
        private readonly ManagerApprovals $approvals,
        private readonly BillSettlement $settlement,
    ) {}

    /**
     * @throws PosNotAllowed
     */
    public function handle(PosBill $bill, CompReason $reason, ?string $note, PosSession $session, int $userId, bool $mayComp, ?int $approvalId = null): PosBill
    {
        return $this->transaction(function () use ($bill, $reason, $note, $session, $userId, $mayComp, $approvalId): PosBill {
            $locked = PosBill::query()->lockForUpdate()->findOrFail($bill->id);

            if ($locked->status !== BillStatus::Printed || (float) $locked->paid_total > 0) {
                throw new PosNotAllowed(__('Only an unpaid printed bill can be made complimentary.'));
            }

            if (! $session->isOpen() || $session->outlet_id !== $locked->outlet_id) {
                throw new PosNotAllowed(__('Open a cash session on this terminal to take payments.'));
            }

            if ($reason === CompReason::Other && trim((string) $note) === '') {
                throw new PosNotAllowed(__('Say why the bill is complimentary.'));
            }

            $approval = $mayComp ? null : ($approvalId !== null
                ? $this->approvals->consume($approvalId, self::APPROVAL, $userId, 'pos_bill', $locked->id)
                : throw PosNotAllowed::needsApproval(__('A complimentary bill needs a manager\'s approval.'), self::APPROVAL));

            PosPayment::query()->create([
                'property_id' => $locked->property_id, 'outlet_id' => $locked->outlet_id, 'pos_bill_id' => $locked->id, 'pos_session_id' => $session->id,
                'business_date' => $session->business_date, 'method' => PaymentMethod::Complimentary, 'amount' => $locked->grand_total, 'created_by' => $userId,
            ]);
            $locked->forceFill([
                'is_complimentary' => true, 'comp_reason' => $reason, 'comp_note' => trim((string) $note) ?: null, 'paid_total' => $locked->grand_total,
                'manager_approval_id' => $approval?->id,
            ])->save();
            $this->settlement->settleIfPaid($locked, $userId);

            return $locked;
        });
    }
}
