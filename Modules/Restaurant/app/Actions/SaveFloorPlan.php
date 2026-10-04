<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Modules\Restaurant\Exceptions\RestaurantSetupInvalid;
use Modules\Restaurant\Models\DiningArea;
use Modules\Restaurant\Models\DiningTable;
use Modules\Restaurant\Services\FloorPlanGeometry;

/**
 * Saves the table positions dragged on a dining area's floor plan (ARCHITECTURE §5.10.3): every
 * position is snapped to the grid and kept inside the canvas; tables of another area are refused.
 */
class SaveFloorPlan extends Action
{
    public function __construct(
        private readonly FloorPlanGeometry $geometry,
    ) {}

    /**
     * @param  list<array{id: int, x: int|float, y: int|float}>  $positions
     * @return int how many tables moved
     *
     * @throws RestaurantSetupInvalid
     */
    public function handle(DiningArea $area, array $positions): int
    {
        $tables = DiningTable::query()->where('dining_area_id', $area->id)->whereIn('id', array_column($positions, 'id'))->get()->keyBy('id');

        if ($tables->count() !== count(array_unique(array_column($positions, 'id')))) {
            throw new RestaurantSetupInvalid(__('Some of these tables are not in this area.'));
        }

        return $this->transaction(function () use ($positions, $tables): int {
            $moved = 0;

            foreach ($positions as $position) {
                $table = $tables->get($position['id']);
                [$x, $y] = $this->geometry->place($table->shape, $table->seats, $position['x'], $position['y']);

                if ($table->pos_x !== $x || $table->pos_y !== $y) {
                    $table->forceFill(['pos_x' => $x, 'pos_y' => $y])->save();
                    $moved++;
                }
            }

            return $moved;
        });
    }
}
