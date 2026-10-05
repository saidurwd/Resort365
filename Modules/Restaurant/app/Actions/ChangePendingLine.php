<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Modules\Restaurant\Enums\Course;
use Modules\Restaurant\Enums\OrderLineStatus;
use Modules\Restaurant\Exceptions\PosNotAllowed;
use Modules\Restaurant\Models\PosOrderLine;
use Modules\Restaurant\Services\OrderLinePricing;
use Modules\Restaurant\Services\OrderTotals;

/**
 * Changes or removes a line not yet sent to the kitchen: quantity (re-priced), seat, course, hold, notes.
 * A sent line can only be voided (VoidOrderLine).
 */
class ChangePendingLine extends Action
{
    public function __construct(
        private readonly OrderLinePricing $pricing,
        private readonly OrderTotals $totals,
    ) {}

    /**
     * @param  array{quantity?: int, seat?: int|null, course?: string, held?: bool, notes?: string|null}  $changes
     *
     * @throws PosNotAllowed
     */
    public function handle(PosOrderLine $line, array $changes): PosOrderLine
    {
        $this->pending($line);

        return $this->transaction(function () use ($line, $changes): PosOrderLine {
            $quantity = array_key_exists('quantity', $changes) ? max(1, min(99, (int) $changes['quantity'])) : $line->quantity;
            $money = $this->pricing->price($line->unit_price, $quantity, array_map(fn (array $modifier): string => $modifier['price'], $line->modifiers ?? []));

            $line->fill(array_filter([
                'quantity' => $quantity, 'line_total' => $money['line_total'],
                'seat_no' => array_key_exists('seat', $changes) ? $changes['seat'] : $line->seat_no,
                'course' => isset($changes['course']) ? Course::from($changes['course']) : $line->course,
                'is_held' => array_key_exists('held', $changes) ? (bool) $changes['held'] : $line->is_held,
                'notes' => array_key_exists('notes', $changes) ? (trim((string) $changes['notes']) ?: null) : $line->notes,
            ], fn ($value, string $key): bool => $value !== null || in_array($key, ['seat_no', 'notes'], true), ARRAY_FILTER_USE_BOTH))->save();
            $this->totals->refresh($line->order);

            return $line;
        });
    }

    /**
     * @throws PosNotAllowed
     */
    public function remove(PosOrderLine $line): void
    {
        $this->pending($line);

        $this->transaction(function () use ($line): void {
            $order = $line->order;
            $line->delete();
            $this->totals->refresh($order);
        });
    }

    /**
     * @throws PosNotAllowed
     */
    private function pending(PosOrderLine $line): void
    {
        if ($line->status !== OrderLineStatus::Pending || ! $line->order->isOpen()) {
            throw new PosNotAllowed(__('This line went to the kitchen: void it instead.'));
        }
    }
}
