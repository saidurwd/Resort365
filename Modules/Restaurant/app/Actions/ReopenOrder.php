<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Modules\Restaurant\Enums\BillStatus;
use Modules\Restaurant\Enums\OrderStatus;
use Modules\Restaurant\Enums\TableStatus;
use Modules\Restaurant\Events\TableStatusChanged;
use Modules\Restaurant\Exceptions\PosNotAllowed;
use Modules\Restaurant\Models\DiningTable;
use Modules\Restaurant\Models\PosBill;
use Modules\Restaurant\Models\PosOrder;
use Modules\Restaurant\Services\ManagerApprovals;

/**
 * Reopens an order whose bill was printed, to add items or change discounts (ARCHITECTURE §5.10.5): with
 * restaurant.bill.reopen, or a manager's PIN (`bill.reopen`). Only while nothing is paid: the printed
 * bills are voided as reopened (their numbers stay used, rule 3) and the order is open again.
 */
class ReopenOrder extends Action
{
    public const string APPROVAL = 'bill.reopen';

    public function __construct(
        private readonly ManagerApprovals $approvals,
    ) {}

    /**
     * @throws PosNotAllowed
     */
    public function handle(PosOrder $order, int $userId, bool $mayReopen, ?int $approvalId = null): PosOrder
    {
        return $this->transaction(function () use ($order, $userId, $mayReopen, $approvalId): PosOrder {
            $locked = PosOrder::query()->lockForUpdate()->findOrFail($order->id);

            if ($locked->status !== OrderStatus::BillPrinted) {
                throw new PosNotAllowed(__('Only an order with a printed bill can be reopened.'));
            }

            $bills = PosBill::query()->where('pos_order_id', $locked->id)->where('status', '!=', BillStatus::Voided->value)->lockForUpdate()->get();

            if ($bills->contains(fn (PosBill $bill): bool => $bill->status === BillStatus::Settled || (float) $bill->paid_total > 0)) {
                throw new PosNotAllowed(__('Payments were taken on this order: it cannot be reopened.'));
            }

            $approval = $mayReopen ? null : ($approvalId !== null
                ? $this->approvals->consume($approvalId, self::APPROVAL, $userId, 'pos_order', $locked->id)
                : throw PosNotAllowed::needsApproval(__('Reopening a printed bill needs a manager\'s approval.'), self::APPROVAL));

            foreach ($bills as $bill) {
                $bill->forceFill([
                    'status' => BillStatus::Voided, 'voided_at' => now(), 'voided_by' => $userId, 'void_reason' => __('Reopened to change the order'),
                    'manager_approval_id' => $approval?->id,
                ])->save();
            }

            $locked->forceFill(['status' => OrderStatus::Open])->save();

            if ($locked->dining_table_id !== null) {
                DiningTable::query()->whereKey($locked->dining_table_id)->update(['status' => TableStatus::Occupied->value]);
            }

            TableStatusChanged::dispatch($locked->tenant_id, $locked->outlet_id, $locked->dining_table_id === null ? [] : [$locked->dining_table_id]);

            return $locked;
        });
    }
}
