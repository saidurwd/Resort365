<?php

namespace Modules\Restaurant\Services;

use Carbon\CarbonInterface;

/**
 * When a restaurant table is free for a reservation (ARCHITECTURE §5.10.12), without the database: two
 * reservations of one table clash when their time slots (start to start + duration) overlap; a table
 * must seat the party.
 */
class TableAvailability
{
    /**
     * @param  list<array{start: CarbonInterface, minutes: int}>  $others  the table's other active reservations
     */
    public function clashes(CarbonInterface $start, int $minutes, array $others): bool
    {
        $end = $start->copy()->addMinutes($minutes);

        foreach ($others as $other) {
            $otherEnd = $other['start']->copy()->addMinutes($other['minutes']);

            if ($start->lt($otherEnd) && $other['start']->lt($end)) {
                return true;
            }
        }

        return false;
    }

    public function seats(int $seats, int $partySize): bool
    {
        return $partySize <= $seats;
    }
}
