<?php

namespace Modules\Payroll\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Modules\Payroll\Database\Factories\ServiceChargeAllocationFactory;
use Modules\Payroll\Enums\PoolStatus;
use Modules\Payroll\Exceptions\PayrollRuleViolated;

/**
 * One employee's share of a pool (Step 6.6), with a snapshot of who they were. Final once the pool is distributed.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $service_charge_pool_id
 * @property int $employee_id
 * @property string $employee_no
 * @property string $employee_name
 * @property int|null $department_id
 * @property string $weight
 * @property string $amount
 * @property int|null $payslip_id
 * @property-read ServiceChargePool $pool
 */
#[UseFactory(ServiceChargeAllocationFactory::class)]
#[Fillable(['service_charge_pool_id', 'employee_id', 'employee_no', 'employee_name', 'department_id', 'weight', 'amount', 'payslip_id'])]
class ServiceChargeAllocation extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ServiceChargeAllocationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['weight' => 'decimal:4', 'amount' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        $guard = function (self $allocation): void {
            $status = DB::table('service_charge_pools')->where('id', $allocation->service_charge_pool_id)->value('status');

            if ($status === PoolStatus::Distributed->value) {
                throw new PayrollRuleViolated(__('This pool has been distributed through payroll and cannot be changed.'));
            }
        };
        static::updating($guard);
        static::deleting($guard);
    }

    /**
     * @return BelongsTo<ServiceChargePool, $this>
     */
    public function pool(): BelongsTo
    {
        return $this->belongsTo(ServiceChargePool::class, 'service_charge_pool_id');
    }
}
