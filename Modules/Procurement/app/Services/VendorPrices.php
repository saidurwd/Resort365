<?php

namespace Modules\Procurement\Services;

use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;
use Modules\Procurement\Models\VendorItem;

/**
 * A vendor's listed price for an item on a date and quantity: the lowest price among the entries that are valid that
 * day and whose minimum quantity is met.
 */
class VendorPrices
{
    public function price(int $vendorId, int $itemId, CarbonInterface $on, string $quantity = '1'): ?string
    {
        $date = $on->toDateString();
        $best = null;

        foreach (VendorItem::query()->where('vendor_id', $vendorId)->where('item_id', $itemId)->get() as $entry) {
            if (($entry->valid_from !== null && $entry->valid_from->toDateString() > $date) || ($entry->valid_to !== null && $entry->valid_to->toDateString() < $date)) {
                continue;
            }

            if (BigDecimal::of($entry->min_quantity)->isGreaterThan($quantity)) {
                continue;
            }

            if ($best === null || BigDecimal::of($entry->price)->isLessThan($best)) {
                $best = (string) $entry->price;
            }
        }

        return $best;
    }
}
