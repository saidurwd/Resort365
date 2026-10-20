<?php

namespace Modules\Reports\Services\Calculation;

use App\Support\DTOs\Data;

/**
 * The management figures of a period (Step 7.1). Occupancy is a percent with one decimal; money is a decimal string.
 */
final readonly class Kpis extends Data
{
    public function __construct(
        public string $occupancy,
        public string $adr,
        public string $revpar,
        public string $roomRevenue,
        public string $fnbSales,
        public int $fnbCovers,
        public int $roomsOccupied,
        public int $roomsAvailable,
        public int $arrivals,
        public int $departures,
        public int $guests,
    ) {}
}
