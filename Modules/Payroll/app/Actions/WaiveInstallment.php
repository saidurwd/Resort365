<?php

namespace Modules\Payroll\Actions;

use App\Support\Actions\Action;
use App\Support\Tenancy\TenantContext;
use Modules\Payroll\Enums\InstallmentStatus;
use Modules\Payroll\Enums\LoanStatus;
use Modules\Payroll\Events\LoanInstallmentWaived;
use Modules\Payroll\Exceptions\PayrollRuleViolated;
use Modules\Payroll\Models\Loan;
use Modules\Payroll\Models\LoanInstallment;

/**
 * Writes off a scheduled installment (Step 6.5), with a reason: it will not be deducted. Fires `LoanInstallmentWaived` for
 * Accounting; the loan closes when nothing is left to deduct.
 */
class WaiveInstallment extends Action
{
    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * @throws PayrollRuleViolated
     */
    public function handle(LoanInstallment $installment, string $reason): LoanInstallment
    {
        if (trim($reason) === '') {
            throw new PayrollRuleViolated(__('Say why the installment is written off.'));
        }

        return $this->transaction(function () use ($installment, $reason): LoanInstallment {
            $locked = LoanInstallment::query()->lockForUpdate()->with('loan')->findOrFail($installment->id);

            if ($locked->status !== InstallmentStatus::Scheduled) {
                throw new PayrollRuleViolated(__('Only an installment still to be deducted can be written off.'));
            }

            $locked->forceFill(['status' => InstallmentStatus::Waived, 'settled_at' => now(), 'note' => trim($reason)])->save();

            if (! LoanInstallment::query()->where('loan_id', $locked->loan_id)->where('status', InstallmentStatus::Scheduled->value)->exists()) {
                Loan::query()->whereKey($locked->loan_id)->first()?->forceFill(['status' => LoanStatus::Closed])->save();
            }

            event(new LoanInstallmentWaived($this->tenant->tenantOrFail(self::class)->id, $locked->id, $locked->loan->property_id));

            return $locked;
        });
    }
}
