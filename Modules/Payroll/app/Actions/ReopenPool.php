<?php

namespace Modules\Payroll\Actions;

use App\Support\Actions\Action;
use Modules\Payroll\Enums\PoolStatus;
use Modules\Payroll\Exceptions\PayrollRuleViolated;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\ServiceChargeAllocation;
use Modules\Payroll\Models\ServiceChargePool;

/**
 * Takes a pool back from payroll to change it (Step 6.6), unless the month's payroll run has gone for approval.
 */
class ReopenPool extends Action
{
    /**
     * @throws PayrollRuleViolated
     */
    public function handle(ServiceChargePool $pool): ServiceChargePool
    {
        return $this->transaction(function () use ($pool): ServiceChargePool {
            $locked = ServiceChargePool::query()->lockForUpdate()->findOrFail($pool->id);

            if ($locked->status !== PoolStatus::Finalised) {
                throw new PayrollRuleViolated(__('Only a pool waiting for payroll can be opened again.'));
            }

            $run = PayrollRun::query()->where('property_id', $locked->property_id)->whereDate('period', $locked->period->toDateString())->first();

            if ($run instanceof PayrollRun && ! $run->isOpen()) {
                throw new PayrollRuleViolated(__('Payroll run :no is :status already, so the pool can no longer change.', ['no' => $run->run_no, 'status' => mb_strtolower($run->status->label())]));
            }

            ServiceChargeAllocation::query()->where('service_charge_pool_id', $locked->id)->update(['payslip_id' => null]);
            $locked->forceFill(['status' => PoolStatus::Calculated, 'finalised_at' => null, 'finalised_by' => null])->save();

            return $locked;
        });
    }
}
