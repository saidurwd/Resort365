<?php

namespace Modules\Property\Contracts;

/**
 * Tells the Property module whether a room is still needed by bookings. Property binds a version
 * that knows no bookings (NoRoomUsage); the Reservation module replaces it with LockedRoomUsage
 * (future reservation and hold locks).
 */
interface RoomUsage
{
    /**
     * Whether the room has bookings from today on, so it cannot be deleted.
     */
    public function hasFutureBookings(int $roomId): bool;
}
