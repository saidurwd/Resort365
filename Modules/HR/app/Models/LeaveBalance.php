<?php

namespace Modules\HR\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\HR\Database\Factories\LeaveBalanceFactory;
use Modules\HR\Services\LeaveBalanceMath;

/**
 * One employee's year of one leave type (Step 6.3). Available = entitled + carried forward + adjusted - used - pending
 * (`LeaveBalanceMath`).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $employee_id
 * @property int $leave_type_id
 * @property int $year
 * @property string $entitled
 * @property string $carried_forward
 * @property string $adjusted
 * @property string $used
 * @property string $pending
 * @property-read Employee $employee
 * @property-read LeaveType $leaveType
 */
#[UseFactory(LeaveBalanceFactory::class)]
#[Fillable(['employee_id', 'leave_type_id', 'year', 'entitled', 'carried_forward', 'adjusted', 'used', 'pending'])]
class LeaveBalance extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<LeaveBalanceFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['year' => 'integer', 'entitled' => 'decimal:1', 'carried_forward' => 'decimal:1', 'adjusted' => 'decimal:1', 'used' => 'decimal:1', 'pending' => 'decimal:1'];
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

    public function available(): string
    {
        return LeaveBalanceMath::available((string) $this->entitled, (string) $this->carried_forward, (string) $this->adjusted, (string) $this->used, (string) $this->pending);
    }
}
