<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Modules\Restaurant\Enums\TableShape;
use Modules\Restaurant\Enums\TableStatus;
use Modules\Restaurant\Models\DiningArea;
use Modules\Restaurant\Models\DiningTable;
use Modules\Restaurant\Services\FloorPlanGeometry;

/**
 * Adds a table to a dining area (placed on the first free spot of its canvas) or changes its
 * number, seats, shape or area; its position stays inside the canvas for its new size.
 */
class SaveDiningTable extends Action
{
    public function __construct(
        private readonly FloorPlanGeometry $geometry,
    ) {}

    public function handle(DiningArea $area, ?DiningTable $table, string $number, int $seats, TableShape $shape, bool $isActive = true): DiningTable
    {
        if (! $table instanceof DiningTable || $table->dining_area_id !== $area->id) {
            $taken = $area->tables()->get()->map(function (DiningTable $other): array {
                [$w, $h] = $other->shape->size($other->seats);

                return ['x' => $other->pos_x, 'y' => $other->pos_y, 'w' => $w, 'h' => $h];
            })->all();
            [$x, $y] = $this->geometry->freeSpot($shape, $seats, $taken);
        } else {
            [$x, $y] = $this->geometry->place($shape, $seats, $table->pos_x, $table->pos_y);
        }

        $table ??= new DiningTable(['property_id' => $area->property_id, 'outlet_id' => $area->outlet_id, 'status' => TableStatus::Available]);
        $table->fill([
            'dining_area_id' => $area->id, 'number' => strtoupper($number), 'seats' => $seats, 'shape' => $shape, 'pos_x' => $x, 'pos_y' => $y, 'is_active' => $isActive,
        ])->save();

        return $table;
    }
}
