<?php

namespace Modules\HR\Services;

use Modules\HR\Enums\AttendanceSource;
use Modules\HR\Enums\AttendanceStatus;
use Modules\HR\Models\Attendance;
use Modules\HR\Models\Employee;
use Modules\HR\Models\Shift;

/**
 * Works out and stores one day's attendance (Step 6.2); used by manual entry (RecordAttendance).
 */
class AttendanceWriter
{
    public function __construct(private readonly AttendanceCalculator $calculator) {}

    public function write(Employee $employee, string $date, ?string $in, ?string $out, ?Shift $shift, bool $holiday, ?AttendanceStatus $override, ?string $notes, ?int $userId): Attendance
    {
        $in = $override === AttendanceStatus::Absent ? null : $in;
        $out = $override === AttendanceStatus::Absent ? null : $out;
        $result = $this->calculator->calculate($shift?->times(), $in, $out, $holiday);

        $values = [
            'property_id' => $employee->property_id, 'shift_id' => $shift?->id, 'clock_in' => $in !== null && $in !== '' ? $in.':00' : null, 'clock_out' => $out !== null && $out !== '' ? $out.':00' : null,
            'status' => $override ?? $result->status, 'late_minutes' => $result->lateMinutes, 'early_leave_minutes' => $result->earlyLeaveMinutes, 'overtime_minutes' => $result->overtimeMinutes,
            'worked_minutes' => $result->workedMinutes, 'source' => AttendanceSource::Manual, 'attendance_import_id' => null, 'notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : null, 'recorded_by' => $userId,
        ];

        if ($override === AttendanceStatus::Absent) {
            $values['status'] = $shift instanceof Shift ? AttendanceStatus::Absent : AttendanceStatus::DayOff;
        }

        return Attendance::query()->updateOrCreate(['employee_id' => $employee->id, 'work_date' => $date], $values);
    }
}
