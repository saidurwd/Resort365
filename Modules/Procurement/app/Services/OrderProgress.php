<?php

namespace Modules\Procurement\Services;

use Brick\Math\BigDecimal;
use Modules\Procurement\Enums\PurchaseOrderStatus;
use Modules\Procurement\Models\PurchaseOrder;
use Modules\Procurement\Models\PurchaseOrderLine;

/**
 * Keeps an order's status in step with what has been received and billed (Step 5.6): partly received, received, and
 * closed once everything received (less returns) has been billed.
 */
class OrderProgress
{
    public function __construct(private readonly OrderQuantities $quantities) {}

    public function refresh(PurchaseOrder $order): PurchaseOrder
    {
        if (! in_array($order->status, [PurchaseOrderStatus::Approved, PurchaseOrderStatus::Sent, PurchaseOrderStatus::Partial, PurchaseOrderStatus::Received, PurchaseOrderStatus::Closed], true)) {
            return $order;
        }

        $lines = PurchaseOrderLine::query()->where('purchase_order_id', $order->id)->get();
        $any = $lines->contains(fn (PurchaseOrderLine $line): bool => BigDecimal::of($line->received_quantity)->isPositive());
        $all = $lines->every(fn (PurchaseOrderLine $line): bool => ! BigDecimal::of($line->received_quantity)->isLessThan($line->quantity));

        if (! $any) {
            return $order;
        }

        $billed = $lines->every(fn (PurchaseOrderLine $line): bool => ! BigDecimal::of($this->quantities->billed($line->id))->isLessThan(BigDecimal::of($line->received_quantity)->minus($this->quantities->returned($line->id))));
        $status = match (true) {
            $all && $billed => PurchaseOrderStatus::Closed,
            $all => PurchaseOrderStatus::Received,
            default => PurchaseOrderStatus::Partial,
        };

        if ($order->status !== $status) {
            $order->forceFill(['status' => $status])->save();
        }

        return $order;
    }
}
