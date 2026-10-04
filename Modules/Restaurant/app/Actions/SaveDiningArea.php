<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Modules\Restaurant\Exceptions\RestaurantSetupInvalid;
use Modules\Restaurant\Models\DiningArea;
use Modules\Restaurant\Models\Outlet;

/**
 * Adds or renames a dining area of an outlet, or removes an empty one.
 */
class SaveDiningArea extends Action
{
    public function handle(Outlet $outlet, ?DiningArea $area, string $name): DiningArea
    {
        $area ??= new DiningArea(['property_id' => $outlet->property_id, 'outlet_id' => $outlet->id, 'sort_order' => $outlet->areas()->count()]);
        $area->fill(['name' => $name])->save();

        return $area;
    }

    /**
     * @throws RestaurantSetupInvalid
     */
    public function delete(DiningArea $area): void
    {
        if ($area->tables()->exists()) {
            throw new RestaurantSetupInvalid(__('Move or remove the area\'s tables first.'));
        }

        $area->delete();
    }
}
