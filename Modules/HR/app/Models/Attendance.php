<?php

namespace Modules\HR\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\HR\Database\Factories\AttendanceFactory;
use Modules\HR\Enums\AttendanceSource;
use Modules\HR\Enums\AttendanceStatus;

/**
 * One employee's day (Step 6.2): the first punch in and last punch out, and the late, early-leave, overtime and worked
 * minutes worked out against the rostered shift (`AttendanceCalculator`). Times are the property's wall-clock times; a
 * night shift belongs to the date it started and its clock-out is the next morning.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $employee_id
 * @property Carbon $work_date
 * @property int|null $shift_id
 * @property string|null $clock_in
 * @property string|null $clock_out
 * @property AttendanceStatus $status
 * @property int $late_minutes
 * @property int $early_leave_minutes
 * @property int $overtime_minutes
 * @property int $worked_minutes
 * @property AttendanceSource $source
 * @property int|null $attendance_import_id
 * @property string|null $notes
 * @property int|null $recorded_by
 * @property-read Employee $employee
 * @property-read Shift|null $shift
 */
#[UseFactory(AttendanceFactory::class)]
#[Fillable(['property_id', 'employee_id', 'work_date', 'shift_id', 'clock_in', 'clock_out', 'status', 'late_minutes', 'early_leave_minutes', 'overtime_minutes', 'worked_minutes', 'source', 'attendance_import_id', 'notes', 'recorded_by'])]
class Attendance extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<AttendanceFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'work_date' => 'date', 'status' => AttendanceStatus::class, 'source' => AttendanceSource::class, 'late_minutes' => 'integer',
            'early_leave_minutes' => 'integer', 'overtime_minutes' => 'integer', 'worked_minutes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class)->withTrashed();
    }

    /** "08:57" or an empty string. */
    public function in(): string
    {
        return $this->clock_in !== null ? substr($this->clock_in, 0, 5) : '';
    }

    public function out(): string
    {
        return $this->clock_out !== null ? substr($this->clock_out, 0, 5) : '';
    }
}
