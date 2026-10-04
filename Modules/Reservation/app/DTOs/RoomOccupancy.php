<?php

namespace Modules\Reservation\DTOs;

use App\Support\DTOs\Data;

/**
 * One room on a date as bookings see it (Housekeeping's room status board): occupied tonight by a
 * checked-in stay, leaving today (a checked-in stay whose last night was yesterday), arriving today
 * (a booking not checked in yet). The booking shown is the one in house, else the one leaving, else
 * the one arriving.
 */
final readonly class RoomOccupancy extends Data
{
    public function __construct(
        public int $roomId,
        public bool $occupied,
        public bool $departing,
        public bool $arriving,
        public ?int $reservationId,
        public ?string $code,
        public ?string $guestName,
    ) {}
}
