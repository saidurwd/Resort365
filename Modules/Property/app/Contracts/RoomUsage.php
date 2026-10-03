<?php

namespace Modules\Property\Contracts;

/**
 * Tells the Property module whether a room is still needed by bookings. Property binds a version
 * that knows no bookings (NoRoomUsage); the Reservation module binds the real one.
 * TODO(step-1.6): Reservation binds its implementation (future reservation items and inventory locks).
 */
interface RoomUsage
{
    /**
     * Whether the room has bookings from today on, so it cannot be deleted.
     */
    public function hasFutureBookings(int $roomId): bool;
}
