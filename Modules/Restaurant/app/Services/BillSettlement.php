<?php

namespace Modules\Restaurant\Services;

use Brick\Math\BigDecimal;
use Modules\Restaurant\Enums\BillStatus;
use Modules\Restaurant\Enums\OrderStatus;
use Modules\Restaurant\Enums\TableStatus;
use Modules\Restaurant\Events\RestaurantBillSettled;
use Modules\Restaurant\Events\TableStatusChanged;
use Modules\Restaurant\Models\DiningTable;
use Modules\Restaurant\Models\PosBill;
use Modules\Restaurant\Models\PosOrder;
use Modules\Restaurant\Models\PosPayment;

/**
 * Closing a bill once it is paid (inside the caller's transaction): the bill is settled and announced
 * (RestaurantBillSettled); when every bill of the order is settled (or voided), the order is settled and
 * its table is free again.
 */
class BillSettlement
{
    public function settleIfPaid(PosBill $bill, int $userId): bool
    {
        if (BigDecimal::of($bill->paid_total)->isLessThan($bill->grand_total)) {
            return false;
        }

        $bill->forceFill(['status' => BillStatus::Settled, 'settled_at' => now(), 'settled_by' => $userId])->save();
        $payments = PosPayment::query()->where('pos_bill_id', $bill->id)->get()->groupBy(fn (PosPayment $payment): string => $payment->method->value)
            ->map(fn ($group): string => (string) $group->reduce(fn (BigDecimal $sum, PosPayment $payment): BigDecimal => $sum->plus($payment->amount)->plus($payment->tip), BigDecimal::zero()))
            ->all();

        RestaurantBillSettled::dispatch($bill->tenant_id, $bill->property_id, $bill->outlet_id, $bill->id, $bill->pos_order_id, $bill->business_date->toDateString(),
            (string) $bill->grand_total, (string) $bill->service_charge, (string) $bill->tax_total, (string) $bill->tip_total, $bill->is_complimentary, $payments);

        $this->closeOrderIfDone($bill->pos_order_id);

        return true;
    }

    public function closeOrderIfDone(int $orderId): void
    {
        $order = PosOrder::query()->lockForUpdate()->findOrFail($orderId);
        $statuses = PosBill::query()->where('pos_order_id', $orderId)->pluck('status')->map(fn (BillStatus $status): string => $status->value);

        if ($order->status !== OrderStatus::BillPrinted || $statuses->contains(BillStatus::Printed->value)) {
            return;
        }

        $order->forceFill(['status' => $statuses->contains(BillStatus::Settled->value) ? OrderStatus::Settled : OrderStatus::Voided, 'closed_at' => now()])->save();

        if ($order->dining_table_id !== null) {
            DiningTable::query()->whereKey($order->dining_table_id)->update(['status' => TableStatus::Available->value]);
        }

        TableStatusChanged::dispatch($order->tenant_id, $order->outlet_id, $order->dining_table_id === null ? [] : [$order->dining_table_id]);
    }
}
