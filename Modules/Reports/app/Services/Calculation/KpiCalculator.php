<?php

namespace Modules\Reports\Services\Calculation;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\FrontOffice\DTOs\StatTotals;

/**
 * Occupancy, ADR and RevPAR from a period's sums (Step 7.1). Pure.
 *
 * - occupancy = rooms occupied ÷ rooms available;
 * - ADR (average daily rate) = room revenue ÷ rooms occupied;
 * - RevPAR (revenue per available room) = room revenue ÷ rooms available.
 *
 * They are worked out from the sums, never by averaging daily figures, so a month of a quiet day and a full one weighs each room
 * night the same, and several properties add up correctly.
 */
final class KpiCalculator
{
    public function calculate(StatTotals $totals): Kpis
    {
        $revenue = BigDecimal::of($totals->roomRevenue);

        return new Kpis(
            $totals->roomsAvailable > 0 ? (string) BigDecimal::of($totals->roomsOccupied)->multipliedBy(100)->dividedBy($totals->roomsAvailable, 1, RoundingMode::HalfUp) : '0.0',
            $totals->roomsOccupied > 0 ? (string) $revenue->dividedBy($totals->roomsOccupied, 2, RoundingMode::HalfUp) : '0.00',
            $totals->roomsAvailable > 0 ? (string) $revenue->dividedBy($totals->roomsAvailable, 2, RoundingMode::HalfUp) : '0.00',
            (string) $revenue->toScale(2), $totals->fnbSales, $totals->fnbCovers, $totals->roomsOccupied, $totals->roomsAvailable, $totals->arrivals, $totals->departures, $totals->adults + $totals->children,
        );
    }
}
