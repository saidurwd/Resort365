<?php

namespace Modules\Payroll\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Payroll\Database\Factories\ServiceChargePoolFactory;
use Modules\Payroll\Enums\PoolKind;
use Modules\Payroll\Enums\PoolRule;
use Modules\Payroll\Enums\PoolStatus;
use Modules\Payroll\Exceptions\PayrollRuleViolated;

/**
 * A month's service charge or tips at a property, shared among staff through payroll (Step 6.6). Once distributed it is final.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property PoolKind $kind
 * @property Carbon $period
 * @property PoolRule $rule
 * @property string $collected
 * @property string $staff_percent
 * @property string $distributable
 * @property int $employees_count
 * @property PoolStatus $status
 * @property int|null $payroll_run_id
 * @property Carbon|null $finalised_at
 * @property int|null $finalised_by
 * @property Carbon|null $distributed_at
 * @property int|null $created_by
 * @property-read Collection<int, ServiceChargeAllocation> $allocations
 */
#[UseFactory(ServiceChargePoolFactory::class)]
#[Fillable(['property_id', 'kind', 'period', 'rule', 'collected', 'staff_percent', 'distributable', 'employees_count', 'status', 'payroll_run_id', 'finalised_at', 'finalised_by', 'distributed_at', 'created_by'])]
class ServiceChargePool extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<ServiceChargePoolFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => PoolKind::class, 'period' => 'date', 'rule' => PoolRule::class, 'collected' => 'decimal:2', 'staff_percent' => 'decimal:2', 'distributable' => 'decimal:2', 'employees_count' => 'integer',
            'status' => PoolStatus::class, 'finalised_at' => 'datetime', 'distributed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $pool): void {
            $original = $pool->getOriginal('status');
            $was = $original instanceof PoolStatus ? $original : PoolStatus::tryFrom((string) $original);

            if ($was === PoolStatus::Distributed) {
                throw new PayrollRuleViolated(__('This pool has been distributed through payroll and cannot be changed.'));
            }
        });
        static::deleting(function (self $pool): void {
            if ($pool->status === PoolStatus::Distributed) {
                throw new PayrollRuleViolated(__('This pool has been distributed through payroll and cannot be deleted.'));
            }
        });
    }

    /**
     * @return HasMany<ServiceChargeAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(ServiceChargeAllocation::class)->orderBy('employee_name');
    }
}
