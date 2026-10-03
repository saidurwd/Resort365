<?php

namespace Modules\Reservation\Services;

use Modules\Property\DTOs\CottageSummary;
use Modules\Property\DTOs\RoomSummary;
use Modules\Reservation\DTOs\AvailableInventory;

/**
 * What can be sold for a stay, from the property's rooms and cottages and the rooms that have a
 * lock on any night of the stay (ARCHITECTURE §6.2), without database access:
 *
 * - a whole cottage: active, its booking mode sells whole cottages, it has active rooms and all
 *   of them are free — so one booked room makes the cottage unavailable whole;
 * - a room: active and free, in an active cottage whose booking mode sells rooms — so a cottage
 *   booked whole (all its rooms locked) leaves none of its rooms.
 */
class AvailabilityCalculator
{
    /**
     * @param  list<RoomSummary>  $rooms
     * @param  list<CottageSummary>  $cottages
     * @param  list<int>  $lockedRoomIds
     */
    public function calculate(array $rooms, array $cottages, array $lockedRoomIds): AvailableInventory
    {
        $locked = array_fill_keys($lockedRoomIds, true);
        $cottagesById = [];

        foreach ($cottages as $cottage) {
            $cottagesById[$cottage->id] = $cottage;
        }

        $whole = array_values(array_filter($cottages, fn (CottageSummary $cottage): bool => $cottage->isActive
            && $cottage->bookingMode->sellsWhole()
            && $cottage->roomIds !== []
            && array_intersect_key(array_flip($cottage->roomIds), $locked) === []));

        $free = array_values(array_filter($rooms, function (RoomSummary $room) use ($locked, $cottagesById): bool {
            $cottage = $cottagesById[$room->cottageId] ?? null;

            return $room->isActive && ! isset($locked[$room->id])
                && $cottage instanceof CottageSummary && $cottage->isActive && $cottage->bookingMode->sellsRooms();
        }));

        return new AvailableInventory($whole, $free);
    }
}
