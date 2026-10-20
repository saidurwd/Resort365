<?php

namespace Modules\Payroll\Actions;

use App\Support\Actions\Action;
use App\Support\Tenancy\TenantContext;
use Modules\Payroll\Enums\PayMethod;
use Modules\Payroll\Enums\RunStatus;
use Modules\Payroll\Events\PayrollPaid;
use Modules\Payroll\Exceptions\PayrollRuleViolated;
use Modules\Payroll\Models\PayrollRun;

/**
 * Records that an approved payroll run was paid (Step 6.5): the method, the date and a reference. Fires `PayrollPaid` for
 * Accounting. A paid run is final.
 */
class PayPayrollRun extends Action
{
    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * @throws PayrollRuleViolated
     */
    public function handle(PayrollRun $run, PayMethod $method, string $paidOn, ?string $reference, int $userId, ?string $today = null): PayrollRun
    {
        if ($paidOn > ($today ?? now()->toDateString())) {
            throw new PayrollRuleViolated(__('A payment cannot be dated in the future.'));
        }

        return $this->transaction(function () use ($run, $method, $paidOn, $reference, $userId): PayrollRun {
            $locked = PayrollRun::query()->lockForUpdate()->findOrFail($run->id);

            if ($locked->status !== RunStatus::Approved) {
                throw new PayrollRuleViolated(__('Payroll run :no is :status; only an approved run is paid.', ['no' => $locked->run_no, 'status' => mb_strtolower($locked->status->label())]));
            }

            if ($paidOn < $locked->approved_at?->toDateString()) {
                throw new PayrollRuleViolated(__('The payment cannot be dated before the approval.'));
            }

            $locked->forceFill(['status' => RunStatus::Paid, 'paid_on' => $paidOn, 'payment_method' => $method, 'payment_reference' => $reference !== null && trim($reference) !== '' ? trim($reference) : null, 'paid_by' => $userId])->save();
            event(new PayrollPaid($this->tenant->tenantOrFail(self::class)->id, $locked->id, $locked->property_id));

            return $locked;
        });
    }
}
