<?php

namespace Modules\Restaurant\Services;

use Modules\Restaurant\Enums\OrderLineStatus;
use Modules\Restaurant\Events\TableStatusChanged;
use Modules\Restaurant\Models\PosOrder;
use Modules\Restaurant\Models\PosOrderLine;

/**
 * Keeps an order's subtotal in step with its lines (voided lines do not count). Called inside the
 * caller's transaction; the outlet's POS terminals hear that the floor changed (running totals).
 */
class OrderTotals
{
    public function __construct(
        private readonly OrderLinePricing $pricing,
    ) {}

    public function refresh(PosOrder $order): void
    {
        $totals = PosOrderLine::query()->where('pos_order_id', $order->id)->where('status', '!=', OrderLineStatus::Voided->value)->pluck('line_total')
            ->map(fn ($total): string => (string) $total)->values()->all();
        $order->forceFill(['subtotal' => $this->pricing->subtotal($totals)])->save();
        $order->unsetRelation('lines');
        TableStatusChanged::dispatch($order->tenant_id, $order->outlet_id, $order->dining_table_id === null ? [] : [$order->dining_table_id]);
    }
}
