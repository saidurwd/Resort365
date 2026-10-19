<?php

namespace Modules\HR\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\HR\Database\Factories\LeavePolicyFactory;
use Modules\HR\Enums\LeaveAccrual;

/**
 * The rules of a leave type (Step 6.3): days a year, how they accrue, how many carry into the next year, the months of
 * service needed, the employment types it applies to (empty = all) and the longest single request.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $leave_type_id
 * @property string $annual_days
 * @property LeaveAccrual $accrual
 * @property string $carry_forward_max
 * @property int $min_service_months
 * @property list<string>|null $employment_types
 * @property int|null $max_consecutive_days
 * @property-read LeaveType $leaveType
 */
#[UseFactory(LeavePolicyFactory::class)]
#[Fillable(['leave_type_id', 'annual_days', 'accrual', 'carry_forward_max', 'min_service_months', 'employment_types', 'max_consecutive_days'])]
class LeavePolicy extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<LeavePolicyFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['annual_days' => 'decimal:1', 'accrual' => LeaveAccrual::class, 'carry_forward_max' => 'decimal:1', 'min_service_months' => 'integer', 'employment_types' => 'array', 'max_consecutive_days' => 'integer'];
    }

    /**
     * @return BelongsTo<LeaveType, $this>
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}
