<?php

namespace Modules\HR\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Modules\HR\Models\Holiday;
use Modules\HR\Models\Roster;
use Modules\HR\Models\Shift;

/**
 * What was planned for employees on dates (Step 6.2): the rostered shift (or a day off) and the property's holidays.
 */
class ScheduleLookup
{
    /**
     * The roster of employees between two dates. A date with no roster row is missing from the result; a day off is null.
     *
     * @param  list<int>  $employeeIds
     * @return array<int, array<string, Shift|null>> employee id => date => shift
     */
    public function rosters(array $employeeIds, string $from, string $to): array
    {
        $map = [];

        foreach (Roster::query()->with('shift')->whereIn('employee_id', $employeeIds)->whereBetween('work_date', [$from, $to])->get() as $row) {
            $map[$row->employee_id][$row->work_date->toDateString()] = $row->shift;
        }

        return $map;
    }

    /**
     * The holidays of a property between two dates, repeating ones included.
     *
     * @return array<string, Holiday> date => holiday
     */
    public function holidays(int $propertyId, string $from, string $to): array
    {
        $start = CarbonImmutable::parse($from);
        $end = CarbonImmutable::parse($to);
        $found = [];

        foreach (Holiday::query()->where('property_id', $propertyId)->get() as $holiday) {
            if ($holiday->repeats_yearly) {
                for ($year = $start->year; $year <= $end->year; $year++) {
                    $date = $this->onYear($holiday->holiday_date, $year);

                    if ($date >= $from && $date <= $to) {
                        $found[$date] = $holiday;
                    }
                }
            } elseif ($holiday->holiday_date->toDateString() >= $from && $holiday->holiday_date->toDateString() <= $to) {
                $found[$holiday->holiday_date->toDateString()] = $holiday;
            }
        }

        ksort($found);

        return $found;
    }

    private function onYear(CarbonImmutable|Carbon $date, int $year): string
    {
        // 29 February repeats on 28 February in other years.
        $day = $date->month === 2 && $date->day === 29 && ! CarbonImmutable::create($year)->isLeapYear() ? 28 : $date->day;

        return CarbonImmutable::createStrict($year, $date->month, $day)->toDateString();
    }
}
