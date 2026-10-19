<?php

namespace Modules\HR\Actions;

use App\Support\Actions\Action;
use Modules\HR\Enums\AttendanceStatus;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Models\Attendance;
use Modules\HR\Models\Employee;
use Modules\HR\Services\AttendanceWriter;
use Modules\HR\Services\ScheduleLookup;

/**
 * Enters or corrects one employee's day by hand (Step 6.2). The late, early-leave, overtime and worked minutes come from
 * the rostered shift and the holidays; `absent` clears the punches and `half_day` keeps them but marks the day. A manual
 * entry replaces an imported one for that day, and later imports leave it alone.
 */
class RecordAttendance extends Action
{
    public function __construct(
        private readonly AttendanceWriter $writer,
        private readonly ScheduleLookup $schedule,
    ) {}

    /**
     * @throws HrRuleViolated
     */
    public function handle(Employee $employee, string $date, ?string $clockIn, ?string $clockOut, ?AttendanceStatus $override, ?string $notes, ?int $userId): Attendance
    {
        if ($employee->isExited() && $employee->exited_on !== null && $date > $employee->exited_on->toDateString()) {
            throw new HrRuleViolated(__(':name left on :date.', ['name' => $employee->full_name, 'date' => $employee->exited_on->format('d M Y')]));
        }

        if ($date < $employee->joined_on->toDateString()) {
            throw new HrRuleViolated(__(':name joined on :date.', ['name' => $employee->full_name, 'date' => $employee->joined_on->format('d M Y')]));
        }

        if ($override instanceof AttendanceStatus && ! in_array($override, [AttendanceStatus::Absent, AttendanceStatus::HalfDay], true)) {
            throw new HrRuleViolated(__('Only "absent" and "half day" can be set by hand.'));
        }

        if ($override !== AttendanceStatus::Absent && ($clockIn === null || $clockIn === '') && ($clockOut === null || $clockOut === '')) {
            throw new HrRuleViolated(__('Enter a clock-in or a clock-out, or mark the day absent.'));
        }

        return $this->transaction(function () use ($employee, $date, $clockIn, $clockOut, $override, $notes, $userId): Attendance {
            $shift = $this->schedule->rosters([$employee->id], $date, $date)[$employee->id][$date] ?? null;
            $holiday = isset($this->schedule->holidays($employee->property_id, $date, $date)[$date]);

            return $this->writer->write($employee, $date, $clockIn, $clockOut, $shift, $holiday, $override, $notes, $userId);
        });
    }
}
