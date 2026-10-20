<?php

namespace Modules\Payroll\Services;

use Brick\Math\BigDecimal;
use Modules\Payroll\Enums\PoolKind;
use Modules\Payroll\Enums\PoolStatus;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\ServiceChargeAllocation;
use Modules\Payroll\Models\ServiceChargePool;

/**
 * What the pools hand a payroll run (Step 6.6): the shares of the pools finalised for the run's property and month, per
 * employee, and the link from a share to the payslip that paid it.
 */
class PoolShares
{
    /**
     * @return array{shares: array<int, array{service: string, tips: string}>, pools: list<int>}
     */
    public function forRun(PayrollRun $run): array
    {
        $pools = ServiceChargePool::query()->where('property_id', $run->property_id)->whereDate('period', $run->period->toDateString())->where('status', PoolStatus::Finalised->value)->get();
        $shares = [];

        foreach (ServiceChargeAllocation::query()->whereIn('service_charge_pool_id', $pools->pluck('id'))->get() as $allocation) {
            $kind = $pools->firstWhere('id', $allocation->service_charge_pool_id)?->kind;
            $key = $kind === PoolKind::Tips ? 'tips' : 'service';
            $shares[$allocation->employee_id] ??= ['service' => '0', 'tips' => '0'];
            $shares[$allocation->employee_id][$key] = (string) BigDecimal::of($shares[$allocation->employee_id][$key])->plus((string) $allocation->amount)->toScale(2);
        }

        return ['shares' => $shares, 'pools' => $pools->pluck('id')->all()];
    }

    /**
     * @param  list<int>  $poolIds
     */
    public function unlink(array $poolIds): void
    {
        ServiceChargeAllocation::query()->whereIn('service_charge_pool_id', $poolIds)->update(['payslip_id' => null]);
    }

    /**
     * @param  list<int>  $poolIds
     */
    public function link(array $poolIds, int $employeeId, int $payslipId): void
    {
        ServiceChargeAllocation::query()->whereIn('service_charge_pool_id', $poolIds)->where('employee_id', $employeeId)->update(['payslip_id' => $payslipId]);
    }

    /**
     * The employees of the pools who have no payslip in the run.
     *
     * @param  list<int>  $poolIds
     * @return list<string> their names
     */
    public function unpaid(array $poolIds): array
    {
        return ServiceChargeAllocation::query()->whereIn('service_charge_pool_id', $poolIds)->whereNull('payslip_id')->orderBy('employee_name')->pluck('employee_name')->unique()->values()->all();
    }
}
