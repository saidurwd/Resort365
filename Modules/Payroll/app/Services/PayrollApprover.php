<?php

namespace Modules\Payroll\Services;

use App\Support\Tenancy\PropertyContext;
use App\Support\Tenancy\TenantContext;
use Modules\Payroll\Enums\InstallmentStatus;
use Modules\Payroll\Enums\LoanStatus;
use Modules\Payroll\Enums\RunStatus;
use Modules\Payroll\Events\PayrollApproved;
use Modules\Payroll\Models\Loan;
use Modules\Payroll\Models\LoanInstallment;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\Payslip;

/**
 * Carries out the decision on a payroll run (Step 6.5). Approving locks the run, marks the loan and advance installments its
 * payslips deducted as deducted (closing loans that are repaid) and fires `PayrollApproved`; rejecting opens the run again.
 * These are system reactions across the tenant's properties, whoever decides.
 */
class PayrollApprover
{
    public function __construct(
        private readonly PropertyContext $properties,
        private readonly TenantContext $tenant,
    ) {}

    public function approve(PayrollRun $run, int $approvedBy): void
    {
        $this->properties->unrestricted(function () use ($run, $approvedBy): void {
            $locked = PayrollRun::query()->lockForUpdate()->find($run->id);

            if (! $locked instanceof PayrollRun || ! in_array($locked->status, [RunStatus::Calculated, RunStatus::Pending], true)) {
                return;
            }

            foreach (Payslip::query()->where('payroll_run_id', $locked->id)->whereNotNull('installment_ids')->get() as $slip) {
                LoanInstallment::query()->whereIn('id', $slip->installment_ids ?? [])->where('status', InstallmentStatus::Scheduled->value)
                    ->update(['status' => InstallmentStatus::Deducted->value, 'payslip_id' => $slip->id, 'settled_at' => now()]);
            }

            $loanIds = LoanInstallment::query()->whereIn('payslip_id', Payslip::query()->where('payroll_run_id', $locked->id)->pluck('id'))->pluck('loan_id')->unique();

            foreach (Loan::query()->whereIn('id', $loanIds)->get() as $loan) {
                if (! LoanInstallment::query()->where('loan_id', $loan->id)->where('status', InstallmentStatus::Scheduled->value)->exists()) {
                    $loan->forceFill(['status' => LoanStatus::Closed])->save();
                }
            }

            $locked->forceFill(['status' => RunStatus::Approved, 'approved_at' => now(), 'approved_by' => $approvedBy])->save();
            event(new PayrollApproved($this->tenant->tenantOrFail(self::class)->id, $locked->id, $locked->property_id));
        });
    }

    public function reject(PayrollRun $run): void
    {
        $this->properties->unrestricted(function () use ($run): void {
            $locked = PayrollRun::query()->lockForUpdate()->find($run->id);

            if ($locked instanceof PayrollRun && $locked->status === RunStatus::Pending) {
                $locked->forceFill(['status' => RunStatus::Calculated])->save();
            }
        });
    }
}
