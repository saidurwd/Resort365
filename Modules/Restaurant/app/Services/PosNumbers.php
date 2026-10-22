<?php

namespace Modules\Restaurant\Services;

use Modules\Restaurant\Models\Kot;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Models\PosBill;
use Modules\Restaurant\Models\PosOrder;

/**
 * Order and KOT numbers, sequential per outlet and business date (ARCHITECTURE §5.10.6), and bill numbers, sequential per outlet
 * (§5.10.13 rule 3), taken inside the caller's transaction so two terminals never get the same one. The outlet row is locked and keeps
 * the last numbers it gave (Step 7.5): another terminal's numbers are seen through those counters, never by locking other rows (which
 * deadlocked with payments under load). A plain look at the stored rows also covers numbers given without the counters (older data,
 * demo history).
 */
class PosNumbers
{
    public function nextOrderNo(Outlet $outlet, string $businessDate): string
    {
        $counters = $this->lock($outlet);
        $taken = PosOrder::query()->where('outlet_id', $outlet->id)->where('business_date', $businessDate)->count();
        $next = $this->advance($outlet, $counters, $businessDate, 'last_order_no', $taken);

        return $outlet->bill_prefix.'-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    public function nextKotNo(Outlet $outlet, string $businessDate): int
    {
        $counters = $this->lock($outlet);
        $taken = (int) Kot::query()->where('outlet_id', $outlet->id)->where('business_date', $businessDate)->max('kot_no');

        return $this->advance($outlet, $counters, $businessDate, 'last_kot_no', $taken);
    }

    /**
     * The next bill of the outlet: sequential and gap-free for ever (bills are never deleted), e.g. MR-B000042.
     *
     * @return array{int, string} sequence, bill number
     */
    public function nextBill(Outlet $outlet): array
    {
        $counters = $this->lock($outlet);
        $sequence = max($counters->last_bill_sequence, (int) PosBill::query()->where('outlet_id', $outlet->id)->max('sequence')) + 1;
        $this->store($outlet, ['last_bill_sequence' => $sequence]);

        return [$sequence, $outlet->bill_prefix.'-B'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT)];
    }

    /** Locks the outlet row and reads its counters as they are now. */
    private function lock(Outlet $outlet): Outlet
    {
        return Outlet::query()->whereKey($outlet->id)->lockForUpdate()->firstOrFail(['id', 'numbers_date', 'last_order_no', 'last_kot_no', 'last_bill_sequence']);
    }

    /**
     * The next order or KOT number of the day. A later business date starts both counters again; an earlier one (a late correction)
     * counts from the stored rows and leaves today's counters alone.
     *
     * @param  'last_order_no'|'last_kot_no'  $counter
     */
    private function advance(Outlet $outlet, Outlet $counters, string $businessDate, string $counter, int $taken): int
    {
        $day = $counters->numbers_date?->toDateString();

        if ($day !== null && $day > $businessDate) {
            return $taken + 1;
        }

        $sameDay = $day === $businessDate;
        $next = max($sameDay ? (int) $counters->getAttribute($counter) : 0, $taken) + 1;
        $values = ['numbers_date' => $businessDate, 'last_order_no' => $sameDay ? $counters->last_order_no : 0, 'last_kot_no' => $sameDay ? $counters->last_kot_no : 0];
        $this->store($outlet, [...$values, $counter => $next]);

        return $next;
    }

    /**
     * Writes the counters without model events: numbering is not a change to the outlet worth an audit entry.
     *
     * @param  array<string, mixed>  $values
     */
    private function store(Outlet $outlet, array $values): void
    {
        Outlet::query()->whereKey($outlet->id)->update($values);
    }
}
