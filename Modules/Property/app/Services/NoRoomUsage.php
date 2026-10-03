<?php

namespace Modules\Property\Services;

use Modules\Property\Contracts\RoomUsage;

/**
 * Default RoomUsage until the Reservation module exists: no room has bookings.
 */
class NoRoomUsage implements RoomUsage
{
    public function hasFutureBookings(int $roomId): bool
    {
        return false;
    }
}
