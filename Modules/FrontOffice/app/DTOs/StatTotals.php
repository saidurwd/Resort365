<?php

namespace Modules\FrontOffice\DTOs;

use App\Support\DTOs\Data;

/**
 * The night audit statistics of one or more properties added up over a period (Step 7.1). Occupancy, ADR and RevPAR are worked
 * out from these sums, never by averaging the daily percentages. Amounts are decimal strings.
 */
final readonly class StatTotals extends Data
{
    public function __construct(
        public int $days,
        public int $roomsAvailable,
        public int $roomsOccupied,
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
