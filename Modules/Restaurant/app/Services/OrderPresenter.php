<?php

namespace Modules\Restaurant\Services;

use Modules\Restaurant\Enums\OrderLineStatus;
use Modules\Restaurant\Models\Kot;
use Modules\Restaurant\Models\PosOrder;
use Modules\Restaurant\Models\PosOrderLine;

/**
 * An order as the POS screen shows it (JSON): header, lines with status, and the tickets to print.
 */
class OrderPresenter
{
    /**
     * @param  list<int>  $printKotIds  tickets just made that a station prints
     * @return array<string, mixed>
     */
    public function present(PosOrder $order, array $printKotIds = []): array
    {
        $order->loadMissing(['lines', 'table', 'kots']);

        return [
            'id' => $order->id, 'order_no' => $order->order_no, 'type' => $order->order_type->value, 'type_label' => $order->order_type->label(),
            'table' => $order->table?->number, 'table_id' => $order->dining_table_id, 'covers' => $order->covers, 'status' => $order->status->value,
            'subtotal' => $order->subtotal, 'opened_at' => $order->opened_at->toIso8601String(), 'notes' => $order->notes,
            'pending' => $order->lines->where('status', OrderLineStatus::Pending)->where('is_held', false)->count(),
            'held' => $order->lines->where('status', OrderLineStatus::Pending)->where('is_held', true)->pluck('course')->map(fn ($course): string => $course->value)->unique()->values()->all(),
            'lines' => $order->lines->map(fn (PosOrderLine $line): array => [
                'id' => $line->id, 'name' => $line->name_snapshot, 'variant' => $line->variant_snapshot, 'quantity' => $line->quantity, 'unit_price' => $line->unit_price,
                'modifiers' => $line->modifiers ?? [], 'line_total' => $line->line_total, 'course' => $line->course->value, 'course_label' => $line->course->label(),
                'seat' => $line->seat_no, 'notes' => $line->notes, 'status' => $line->status->value, 'status_label' => $line->status->label(), 'status_color' => $line->status->color(), 'held' => $line->is_held,
                'void_reason' => $line->void_reason?->label(),
            ])->values()->all(),
            'kots' => $order->kots->map(fn (Kot $kot): array => ['id' => $kot->id, 'no' => $kot->kot_no, 'type' => $kot->type->value, 'print' => in_array($kot->id, $printKotIds, true),
                'url' => route('pos.kots.print', $kot)])->values()->all(),
        ];
    }
}
