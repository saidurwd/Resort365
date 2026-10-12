<?php

namespace Modules\Procurement\Services;

use Modules\Procurement\Enums\VendorBillStatus;
use Modules\Procurement\Models\GoodsReceiptLine;
use Modules\Procurement\Models\VendorBillLine;

/**
 * How much of an order line has been billed and returned (Step 5.6). A draft, rejected or cancelled bill does not count.
 */
class OrderQuantities
{
    /** Statuses whose bills use up an order line's quantity. */
    private const array BILLING = [VendorBillStatus::Pending, VendorBillStatus::Approved, VendorBillStatus::Partial, VendorBillStatus::Paid];

    public function billed(int $orderLineId, ?int $exceptBillId = null): string
    {
        return (string) VendorBillLine::query()->where('purchase_order_line_id', $orderLineId)
            ->whereHas('bill', fn ($query) => $query->whereIn('status', array_map(fn (VendorBillStatus $status): string => $status->value, self::BILLING))->when($exceptBillId !== null, fn ($inner) => $inner->whereKeyNot($exceptBillId)))
            ->sum('quantity');
    }

    public function returned(int $orderLineId): string
    {
        return (string) GoodsReceiptLine::query()->where('purchase_order_line_id', $orderLineId)->sum('returned_quantity');
    }
}
