<?php

namespace Modules\HR\Services;

use Carbon\CarbonImmutable;
use Closure;
use Modules\HR\DTOs\ShiftTimes;

/**
 * Turns an employee's punches (a date and time each) into days with a first-in and a last-out (Step 6.2). Pure.
 *
 * A punch just after midnight belongs to the day before when that day was a night shift that has no clock-out yet and
 * the punch is no more than four hours after the shift's end. Everything else belongs to its own date; the first punch
 * of a day is the clock-in and the last is the clock-out (one punch only leaves the clock-out empty).
 */
class PunchGrouper
{
    private const int TOLERANCE_MINUTES = 240;

    /**
     * @param  list<string>  $punches  "Y-m-d H:i" in any order
     * @param  Closure(string): ?int  $nightEnd  for a date, the end of the night shift that started that day (minutes after its midnight, e.g. 360 for 06:00), or null
     * @return array<string, array{in: string, out: string|null}> work date => times
     */
    public function group(array $punches, Closure $nightEnd): array
    {
        sort($punches);
        $days = [];

        foreach (array_unique($punches) as $punch) {
            [$date, $time] = explode(' ', $punch);
            $previous = CarbonImmutable::parse($date)->subDay()->toDateString();
            $end = isset($days[$previous]) && $days[$previous]['out'] === null ? $nightEnd($previous) : null;

            if ($end !== null && ShiftTimes::minutes($time) <= ($end % 1440) + self::TOLERANCE_MINUTES && ShiftTimes::minutes($time) < 720) {
                $days[$previous]['out'] = $time;

                continue;
            }

            if (isset($days[$date])) {
                $days[$date]['out'] = $time;
            } else {
                $days[$date] = ['in' => $time, 'out' => null];
            }
        }

        return $days;
    }
}
