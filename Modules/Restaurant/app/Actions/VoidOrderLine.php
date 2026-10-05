<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Modules\Restaurant\Enums\KotStatus;
use Modules\Restaurant\Enums\KotType;
use Modules\Restaurant\Enums\OrderLineStatus;
use Modules\Restaurant\Enums\VoidReason;
use Modules\Restaurant\Exceptions\PosNotAllowed;
use Modules\Restaurant\Models\Kot;
use Modules\Restaurant\Models\KotLine;
use Modules\Restaurant\Models\PosOrderLine;
use Modules\Restaurant\Services\ManagerApprovals;
use Modules\Restaurant\Services\OrderTotals;
use Modules\Restaurant\Services\PosNumbers;

/**
 * Voids a line already sent to the kitchen (ARCHITECTURE §5.10.6): never deleted, voided with a reason.
 * Without restaurant.order.void a manager's PIN approval is needed (ManagerApprovals, used once). A
 * void KOT tells the station; "already made" records the line as wastage. The order total drops.
 */
class VoidOrderLine extends Action
{
    public const string APPROVAL = 'order.void-line';

    public function __construct(
        private readonly ManagerApprovals $approvals,
        private readonly PosNumbers $numbers,
        private readonly OrderTotals $totals,
    ) {}

    /**
     * @throws PosNotAllowed
     */
    public function handle(PosOrderLine $line, VoidReason $reason, ?string $note, bool $wastage, int $userId, bool $mayVoid, ?int $approvalId = null): Kot
    {
        return $this->transaction(function () use ($line, $reason, $note, $wastage, $userId, $mayVoid, $approvalId): Kot {
            $locked = PosOrderLine::query()->lockForUpdate()->findOrFail($line->id);
            $order = $locked->order()->with('outlet')->firstOrFail();

            if ($locked->status === OrderLineStatus::Pending) {
                throw new PosNotAllowed(__('This line was not sent yet: just remove it.'));
            }

            if ($locked->status === OrderLineStatus::Voided || ! $order->isOpen()) {
                throw new PosNotAllowed(__('This line cannot be voided.'));
            }

            if ($reason === VoidReason::Other && trim((string) $note) === '') {
                throw new PosNotAllowed(__('Say why the line is voided.'));
            }

            $approval = $mayVoid ? null : ($approvalId !== null
                ? $this->approvals->consume($approvalId, self::APPROVAL, $userId, 'pos_order_line', $locked->id)
                : throw new PosNotAllowed(__('Voiding needs a manager\'s approval.')));

            $locked->forceFill([
                'status' => OrderLineStatus::Voided, 'void_reason' => $reason, 'void_note' => trim((string) $note) ?: null, 'voided_by' => $userId,
                'voided_at' => now(), 'manager_approval_id' => $approval?->id, 'is_wastage' => $wastage,
            ])->save();

            $kot = Kot::query()->create([
                'property_id' => $order->property_id, 'outlet_id' => $order->outlet_id, 'pos_order_id' => $order->id, 'kitchen_station_id' => $locked->kitchen_station_id,
                'kot_no' => $this->numbers->nextKotNo($order->outlet, $order->business_date->toDateString()), 'business_date' => $order->business_date,
                'type' => KotType::Void, 'status' => KotStatus::New, 'fired_at' => now(), 'created_by' => $userId,
            ]);
            KotLine::query()->create(['property_id' => $order->property_id, 'kot_id' => $kot->id, 'pos_order_line_id' => $locked->id, 'quantity' => $locked->quantity, 'status' => KotStatus::New]);
            $this->totals->refresh($order);

            return $kot;
        });
    }
}
