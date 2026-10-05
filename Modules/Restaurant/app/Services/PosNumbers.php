<?php

namespace Modules\Restaurant\Services;

use Modules\Restaurant\Models\Kot;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Models\PosOrder;

/**
 * Order and KOT numbers, sequential per outlet and business date (ARCHITECTURE §5.10.6): taken under a
 * lock on the outlet row, inside the caller's transaction, so two terminals never get the same one.
 */
class PosNumbers
{
    public function nextOrderNo(Outlet $outlet, string $businessDate): string
    {
        $this->lock($outlet);
        $last = PosOrder::query()->where('outlet_id', $outlet->id)->where('business_date', $businessDate)->count();

        return $outlet->bill_prefix.'-'.str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }

    public function nextKotNo(Outlet $outlet, string $businessDate): int
    {
        $this->lock($outlet);

        return (int) Kot::query()->where('outlet_id', $outlet->id)->where('business_date', $businessDate)->max('kot_no') + 1;
    }

    private function lock(Outlet $outlet): void
    {
        Outlet::query()->whereKey($outlet->id)->lockForUpdate()->value('id');
    }
}
