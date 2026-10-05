<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Modules\Restaurant\Events\MenuAvailabilityChanged;
use Modules\Restaurant\Models\OutletMenuItem;

/**
 * Marks an outlet's item as sold out ("86") or back on sale (ARCHITECTURE §5.10.2). Every POS screen of
 * the outlet greys the item out (or back in) at once (MenuAvailabilityChanged, Step 3.5).
 */
class MarkSoldOut extends Action
{
    public function handle(OutletMenuItem $row, bool $soldOut): OutletMenuItem
    {
        $row->forceFill(['is_available' => ! $soldOut])->save();
        $name = $row->item->translated('name');

        MenuAvailabilityChanged::dispatch($row->tenant_id, $row->outlet_id,
            $soldOut ? __(':item is sold out.', ['item' => $name]) : __(':item is back on sale.', ['item' => $name]));

        return $row;
    }
}
