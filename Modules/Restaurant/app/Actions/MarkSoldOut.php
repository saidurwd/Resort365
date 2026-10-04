<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Modules\Restaurant\Models\OutletMenuItem;

/**
 * Marks an outlet's item as sold out ("86") or back on sale (ARCHITECTURE §5.10.2).
 * TODO(step-3.4): broadcast it so every POS screen greys the item out at once (Reverb).
 */
class MarkSoldOut extends Action
{
    public function handle(OutletMenuItem $row, bool $soldOut): OutletMenuItem
    {
        $row->forceFill(['is_available' => ! $soldOut])->save();

        return $row;
    }
}
