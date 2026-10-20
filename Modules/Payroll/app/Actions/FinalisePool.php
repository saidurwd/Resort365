<?php

namespace Modules\Payroll\Actions;

use App\Support\Actions\Action;
use Modules\Payroll\Enums\PoolStatus;
use Modules\Payroll\Enums\RunStatus;
use Modules\Payroll\Exceptions\PayrollRuleViolated;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\ServiceChargePool;

/**
 * Hands a worked-out pool to payroll (Step 6.6): the payroll run for that month adds the shares when it is calculated. Returns
 * whether a run already exists for the month, which must be calculated again to include them.
 */
class FinalisePool extends Action
{
    /**
     * @throws PayrollRuleViolated
     */
    public function handle(ServiceChargePool $pool, ?int $userId): bool
    {
        return $this->transaction(function () use ($pool, $userId): bool {
            $locked = ServiceChargePool::query()->lockForUpdate()->findOrFail($pool->id);

            if ($locked->status !== PoolStatus::Calculated) {
                throw new PayrollRuleViolated(__('Only a pool that has been worked out can be sent to payroll.'));
            }

            if ($locked->allocations()->count() === 0) {
                throw new PayrollRuleViolated(__('There is nothing to distribute in this pool.'));
            }

            $run = PayrollRun::query()->where('property_id', $locked->property_id)->whereDate('period', $locked->period->toDateString())->first();

            if ($run instanceof PayrollRun && ! $run->isOpen()) {
                throw new PayrollRuleViolated(__('Payroll run :no is :status already, so it can no longer take this pool.', ['no' => $run->run_no, 'status' => mb_strtolower($run->status->label())]));
            }

            $locked->forceFill(['status' => PoolStatus::Finalised, 'finalised_at' => now(), 'finalised_by' => $userId])->save();

            return $run instanceof PayrollRun && $run->status === RunStatus::Calculated;
        });
    }
}
