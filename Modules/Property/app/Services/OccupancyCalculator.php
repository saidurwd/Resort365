<?php

namespace Modules\Property\Services;

use Modules\Property\DTOs\RoomCapacity;
use Modules\Property\Models\Cottage;
use Modules\Property\Models\Room;

/**
 * Room and cottage capacity (ARCHITECTURE §5.4):
 * - a room's maximum adults and children are its own values, or its room type's when empty;
 *   the total never exceeds the room type's max_occupancy;
 * - a cottage's maximum occupancy is its override, or the sum of its active rooms.
 */
class OccupancyCalculator
{
    public function room(?int $roomAdults, ?int $roomChildren, int $typeAdults, int $typeChildren, int $typeMaxOccupancy): RoomCapacity
    {
        $adults = $roomAdults ?? $typeAdults;
        $children = $roomChildren ?? $typeChildren;

        return new RoomCapacity(
            maxAdults: min($adults, $typeMaxOccupancy),
            maxChildren: min($children, $typeMaxOccupancy),
            maxOccupancy: min($adults + $children, $typeMaxOccupancy),
        );
    }

    /**
     * @param  iterable<int>  $roomOccupancies  maximum occupancy of each active room
     */
    public function cottage(?int $override, iterable $roomOccupancies): int
    {
        if ($override !== null) {
            return $override;
        }

        $total = 0;

        foreach ($roomOccupancies as $occupancy) {
            $total += $occupancy;
        }

        return $total;
    }

    /**
     * Capacity of a room (loads its room type when needed).
     */
    public function forRoom(Room $room): RoomCapacity
    {
        $type = $room->roomType;

        return $this->room($room->max_adults, $room->max_children, $type->max_adults, $type->max_children, $type->max_occupancy);
    }

    /**
     * Maximum occupancy of a cottage (loads its rooms and their room types when needed).
     */
    public function forCottage(Cottage $cottage): int
    {
        $occupancies = $cottage->rooms->where('is_active', true)->map(fn (Room $room): int => $this->forRoom($room)->maxOccupancy);

        return $this->cottage($cottage->max_occupancy_override, $occupancies);
    }
}
