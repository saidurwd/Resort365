<?php

namespace Modules\Reservation\DTOs;

use App\Support\DTOs\Data;

/**
 * One property's night as stayed (night audit statistics, ARCHITECTURE §5.7 step 7). Occupied rooms
 * and guests count rooms in house that night (checked in, or checked out since); revenue is the
 * nights' prices frozen at booking: room revenue is the net without the meal component, which is
 * F&B package revenue. Amounts are decimal strings.
 */
final readonly class NightOccupancy extends Data
{
    public function __construct(
        public string $date,
        public int $roomsOccupied,
        public int $roomsOutOfOrder,
        public int $roomsBlocked,
        public int $adults,
        public int $children,
        public string $roomRevenue,
        public string $packageMealRevenue,
        public string $roomTax,
        public int $arrivals,
        public int $departures,
        public int $noShows,
    ) {}
}
