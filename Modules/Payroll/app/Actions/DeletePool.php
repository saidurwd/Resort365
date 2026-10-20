<?php

namespace Modules\Payroll\Actions;

use App\Support\Actions\Action;
use Modules\Payroll\Enums\PoolStatus;
use Modules\Payroll\Exceptions\PayrollRuleViolated;
use Modules\Payroll\Models\ServiceChargeAllocation;
use Modules\Payroll\Models\ServiceChargePool;

/**
 * Deletes a pool that has not been distributed (Step 6.6), with its shares.
 */
class DeletePool extends Action
{
    /**
     * @throws PayrollRuleViolated
     */
    public function handle(ServiceChargePool $pool): void
    {
        $this->transaction(function () use ($pool): void {
            $locked = ServiceChargePool::query()->lockForUpdate()->findOrFail($pool->id);

            if ($locked->status === PoolStatus::Distributed) {
                throw new PayrollRuleViolated(__('This pool has been distributed through payroll and cannot be deleted.'));
            }

            ServiceChargeAllocation::query()->where('service_charge_pool_id', $locked->id)->update(['payslip_id' => null]);
            ServiceChargeAllocation::query()->where('service_charge_pool_id', $locked->id)->delete();
            $locked->delete();
        });
    }
}
