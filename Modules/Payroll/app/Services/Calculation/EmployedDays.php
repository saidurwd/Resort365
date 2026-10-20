<?php

namespace Modules\Payroll\Services\Calculation;

use Carbon\CarbonImmutable;

/**
 * The days of a month a person was employed (Step 6.5, 6.6): from the day they joined, to the day they left. Pure.
 */
final class EmployedDays
{
    public static function count(string $joinedOn, ?string $exitedOn, CarbonImmutable $start, CarbonImmutable $end): int
    {
        $from = max($joinedOn, $start->toDateString());
        $to = $exitedOn !== null && $exitedOn < $end->toDateString() ? $exitedOn : $end->toDateString();

        return max(0, (int) CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) + 1);
    }
}
