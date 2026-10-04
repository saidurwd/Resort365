<?php

namespace Modules\FrontOffice\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Room statistics for one night (ARCHITECTURE §5.7 step 7, USALI), without the database. Rooms out
 * of order are not available to sell; owner blocks and holds are (they are just not sold).
 * Occupancy % = occupied / available; ADR = room revenue / occupied; RevPAR = room revenue /
 * available. Each is rounded half-up to two decimals; nothing to divide by gives 0.
 */
class DailyStatsCalculator
{
    /**
     * @return array{rooms_available: int, occupancy_percent: string, adr: string, revpar: string}
     */
    public function calculate(int $roomsTotal, int $roomsOutOfOrder, int $roomsOccupied, string $roomRevenue): array
    {
        $available = max(0, $roomsTotal - $roomsOutOfOrder);
        $revenue = BigDecimal::of($roomRevenue);
        $ratio = fn (BigDecimal $amount, int $by): string => $by > 0 ? (string) $amount->dividedBy($by, 2, RoundingMode::HalfUp) : '0.00';

        return [
            'rooms_available' => $available,
            'occupancy_percent' => $ratio(BigDecimal::of($roomsOccupied)->multipliedBy(100), $available),
            'adr' => $ratio($revenue, $roomsOccupied),
            'revpar' => $ratio($revenue, $available),
        ];
    }
}
