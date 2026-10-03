<?php

namespace Modules\Property\Services;

use Modules\Property\Contracts\RoomUsage;

/**
 * Default RoomUsage when the Reservation module is not installed: no room has bookings.
 */
class NoRoomUsage implements RoomUsage
{
    public function hasFutureBookings(int $roomId): bool
    {
        return false;
    }
}
