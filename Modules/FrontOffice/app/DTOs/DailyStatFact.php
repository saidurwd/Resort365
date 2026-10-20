<?php

namespace Modules\FrontOffice\DTOs;

use App\Support\DTOs\Data;

/**
 * One property's day as the night audit counted it (Step 7.1): rooms, occupancy, average rate, revenue, F&B and movements.
 * Amounts are decimal strings.
 */
final readonly class DailyStatFact extends Data
{
    public function __construct(
        public int $propertyId,
        public string $date,
        public int $roomsAvailable,
        public int $roomsOccupied,
        public string $occupancyPercent,
        public string $adr,
        public string $revpar,
        public string $roomRevenue,
        public string $packageMealRevenue,
        public string $fnbSales,
        public int $fnbCovers,
        public string $received,
        public string $refunded,
        public int $arrivals,
        public int $departures,
        public int $noShows,
        public int $adults,
        public int $children,
    ) {}
}
