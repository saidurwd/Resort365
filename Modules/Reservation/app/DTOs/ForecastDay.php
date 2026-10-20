<?php

namespace Modules\Reservation\DTOs;

use App\Support\DTOs\Data;

/**
 * One night on the books for one or more properties (Step 7.1): the active rooms, those out of order or blocked, those booked or
 * held, and the bookings arriving.
 */
final readonly class ForecastDay extends Data
{
    public function __construct(
        public string $date,
        public int $roomsTotal,
        public int $roomsBlocked,
        public int $roomsBooked,
        public int $arrivals,
    ) {}
}
