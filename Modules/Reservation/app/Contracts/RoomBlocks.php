<?php

namespace Modules\Reservation\Contracts;

use Modules\Reservation\Exceptions\RoomBlockRefused;

/**
 * Takes rooms off sale for other modules (ARCHITECTURE §5.11, §6.3): Housekeeping's out-of-order
 * blocks lock the room's nights in inventory_locks (lock_type out_of_order, block_id), so the room
 * cannot be sold. Availability stays in one table.
 */
interface RoomBlocks
{
    /**
     * Locks a room for the nights [from, to) (Y-m-d) under a block; all nights or none.
     *
     * @throws RoomBlockRefused naming the bookings or blocks that hold any of the nights
     */
    public function block(int $propertyId, int $roomId, string $from, string $to, int $blockId, string $note): void;

    /**
     * Releases a block's nights from $from (Y-m-d) on, or all of them.
     *
     * @return int how many nights were released
     */
    public function release(int $blockId, ?string $from = null): int;
}
