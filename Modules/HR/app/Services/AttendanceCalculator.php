<?php

namespace Modules\HR\Services;

use Modules\HR\DTOs\AttendanceResult;
use Modules\HR\DTOs\ShiftTimes;
use Modules\HR\Enums\AttendanceStatus;

/**
 * Works out a day's attendance from the rostered shift and the punches (Step 6.2). Pure.
 *
 * - Worked minutes run from the first punch to the last, less the shift's unpaid break (a clock-out at or before the
 *   clock-in means the next day).
 * - Late: arriving after the start plus the grace minutes counts every minute from the start. Early leave: leaving
 *   before the end less the grace minutes counts every minute before the end. Arriving early or leaving late is not
 *   negative.
 * - Overtime: minutes after the end, once they reach the shift's overtime threshold (at least one minute).
 * - On a holiday or a day with no shift, all the time worked is overtime and nobody is late.
 * - One punch only is a missing punch to look at (status incomplete), not an absence; no punch at all is an absence on a
 *   rostered day, a holiday on a holiday, a day off otherwise.
 */
class AttendanceCalculator
{
    public function calculate(?ShiftTimes $shift, ?string $clockIn, ?string $clockOut, bool $holiday = false): AttendanceResult
    {
        $in = $clockIn !== null && $clockIn !== '' ? ShiftTimes::minutes($clockIn) : null;
        $out = $clockOut !== null && $clockOut !== '' ? ShiftTimes::minutes($clockOut) : null;
        $off = $holiday || ! $shift instanceof ShiftTimes;

        if ($in === null && $out === null) {
            return new AttendanceResult($holiday ? AttendanceStatus::Holiday : ($shift instanceof ShiftTimes ? AttendanceStatus::Absent : AttendanceStatus::DayOff));
        }

        if ($in === null || $out === null) {
            $late = ! $off && $in !== null ? $this->late($shift, $in) : 0;

            return new AttendanceResult(AttendanceStatus::Incomplete, lateMinutes: $late);
        }

        if ($out <= $in) {
            $out += 1440;
        }

        $worked = $out - $in;

        if ($shift instanceof ShiftTimes && $worked > $shift->breakMinutes) {
            $worked -= $shift->breakMinutes;
        }

        if ($off) {
            return new AttendanceResult($holiday ? AttendanceStatus::Holiday : AttendanceStatus::DayOff, overtimeMinutes: $worked, workedMinutes: $worked);
        }

        $late = $this->late($shift, $in);
        $early = $shift->endMinutes() - $out > $shift->graceMinutes ? $shift->endMinutes() - $out : 0;
        $extra = $out - $shift->endMinutes();
        $overtime = $extra > 0 && $extra >= max(1, $shift->overtimeAfterMinutes) ? $extra : 0;

        return new AttendanceResult($late > 0 ? AttendanceStatus::Late : AttendanceStatus::Present, $late, $early, $overtime, $worked);
    }

    private function late(ShiftTimes $shift, int $in): int
    {
        $after = $in - $shift->startMinutes();

        return $after > $shift->graceMinutes ? $after : 0;
    }
}
