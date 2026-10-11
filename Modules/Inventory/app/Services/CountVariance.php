<?php

namespace Modules\Inventory\Services;

use Brick\Math\BigDecimal;
use Modules\Inventory\Models\StockCountLine;
use Modules\Inventory\Models\StockMovement;

/**
 * What a count line should have shown, allowing for movements since the count started (Step 5.4): the frozen system
 * quantity plus every movement after the snapshot. The variance is the counted quantity less that.
 */
class CountVariance
{
    public function expected(StockCountLine $line, int $storeId): string
    {
        $since = StockMovement::query()->where('store_id', $storeId)->where('item_id', $line->item_id)->where('id', '>', $line->snapshot_movement_id)->sum('quantity');

        return (string) BigDecimal::of($line->system_quantity)->plus((string) $since)->toScale(4);
    }

    /**
     * @return string|null signed difference (counted less expected), null when the line was not counted
     */
    public function variance(StockCountLine $line, int $storeId): ?string
    {
        return $line->counted_quantity === null ? null : (string) BigDecimal::of($line->counted_quantity)->minus($this->expected($line, $storeId))->toScale(4);
    }
}
