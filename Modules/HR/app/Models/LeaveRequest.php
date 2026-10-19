<?php

namespace Modules\HR\Models;

use App\Support\Attachments\HasAttachments;
use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\HR\Database\Factories\LeaveRequestFactory;
use Modules\HR\Enums\HalfDay;
use Modules\HR\Enums\LeaveStatus;
use Spatie\MediaLibrary\HasMedia;

/**
 * An employee's request for leave (Step 6.3), approved through the `leave_request` workflow. `charged_dates` are the days
 * that are charged to the balance (holidays and days off are not, unless the type says so) and `days_by_year` how many of
 * them fall in each calendar year. A medical certificate or other paper is an attachment.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property string $request_no
 * @property int $employee_id
 * @property int $leave_type_id
 * @property Carbon $from_date
 * @property Carbon $to_date
 * @property string $days
 * @property HalfDay|null $half_day
 * @property list<string> $charged_dates
 * @property array<string, string> $days_by_year
 * @property string|null $reason
 * @property LeaveStatus $status
 * @property int|null $approval_request_id
 * @property int $requested_by
 * @property Carbon|null $decided_at
 * @property Carbon|null $cancelled_at
 * @property-read Employee $employee
 * @property-read LeaveType $leaveType
 */
#[UseFactory(LeaveRequestFactory::class)]
#[Fillable(['property_id', 'request_no', 'employee_id', 'leave_type_id', 'from_date', 'to_date', 'days', 'half_day', 'charged_dates', 'days_by_year', 'reason', 'status', 'approval_request_id', 'requested_by', 'decided_at', 'cancelled_at'])]
class LeaveRequest extends Model implements HasMedia
{
    use BelongsToProperty;
    use BelongsToTenant;
    use HasAttachments;

    /** @use HasFactory<LeaveRequestFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_date' => 'date', 'to_date' => 'date', 'days' => 'decimal:1', 'half_day' => HalfDay::class, 'charged_dates' => 'array', 'days_by_year' => 'array',
            'status' => LeaveStatus::class, 'decided_at' => 'datetime', 'cancelled_at' => 'datetime',
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
     * @return BelongsTo<LeaveType, $this>
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class)->withTrashed();
    }
}
