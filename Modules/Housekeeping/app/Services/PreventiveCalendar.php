<?php

namespace Modules\Housekeeping\Services;

use Carbon\CarbonImmutable;

/**
 * Preventive maintenance dates (ARCHITECTURE §5.11), without the database: a schedule is due on
 * next_due_on; once its work order is created the next date moves on by whole intervals until it
 * is after the day the work order was made, so a schedule missed for a while opens one work order,
 * not one per missed interval.
 */
class PreventiveCalendar
{
    public function isDue(string $nextDueOn, string $today): bool
    {
        return $nextDueOn <= $today;
    }

    public function following(string $dueOn, int $intervalDays, string $today): string
    {
        $intervalDays = max(1, $intervalDays);
        $next = CarbonImmutable::parse($dueOn)->addDays($intervalDays);

        while ($next->toDateString() <= $today) {
            $next = $next->addDays($intervalDays);
        }

        return $next->toDateString();
    }
}
