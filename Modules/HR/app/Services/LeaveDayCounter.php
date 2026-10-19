<?php

namespace Modules\HR\Services;

use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;

/**
 * Counts the days a leave request is charged for (Step 6.3). Pure.
 *
 * Every date from the first to the last is charged unless it is in `$notWorked` (holidays and rostered days off) and the
 * leave type does not count non-working days. A half day is a single date charged as 0.5.
 */
final class LeaveDayCounter
{
    /**
     * @param  array<string, true>  $notWorked  dates (Y-m-d) that are holidays or rostered days off
     * @return array{dates: list<string>, days: string, byYear: array<string, string>}
     */
    public static function count(string $from, string $to, bool $halfDay, bool $countsNonWorkingDays, array $notWorked): array
    {
        $dates = [];

        for ($day = CarbonImmutable::parse($from); $day->lte(CarbonImmutable::parse($to)); $day = $day->addDay()) {
            $date = $day->toDateString();

            if ($countsNonWorkingDays || ! isset($notWorked[$date])) {
                $dates[] = $date;
            }
        }

        $each = $halfDay ? BigDecimal::of('0.5') : BigDecimal::one();
        $byYear = [];

        foreach ($dates as $date) {
            $year = substr($date, 0, 4);
            $byYear[$year] = (string) BigDecimal::of($byYear[$year] ?? '0')->plus($each)->toScale(1);
        }

        return ['dates' => $dates, 'days' => (string) $each->multipliedBy(count($dates))->toScale(1), 'byYear' => $byYear];
    }
}
