<?php

namespace Modules\Payroll\Actions;

use App\Support\Actions\Action;
use Modules\Payroll\Enums\InstallmentStatus;
use Modules\Payroll\Exceptions\PayrollRuleViolated;
use Modules\Payroll\Models\LoanInstallment;

/**
 * Moves a scheduled installment to the end of the loan (Step 6.5), one month after the last; the loan is repaid a month later.
 */
class SkipInstallment extends Action
{
    /**
     * @throws PayrollRuleViolated
     */
    public function handle(LoanInstallment $installment): LoanInstallment
    {
        return $this->transaction(function () use ($installment): LoanInstallment {
            $locked = LoanInstallment::query()->lockForUpdate()->findOrFail($installment->id);

            if ($locked->status !== InstallmentStatus::Scheduled) {
                throw new PayrollRuleViolated(__('Only an installment still to be deducted can be moved.'));
            }

            $last = LoanInstallment::query()->where('loan_id', $locked->loan_id)->orderByDesc('due_month')->orderByDesc('sequence')->firstOrFail();
            $locked->forceFill(['due_month' => $last->due_month->addMonthNoOverflow()->startOfMonth()->toDateString(), 'sequence' => (int) LoanInstallment::query()->where('loan_id', $locked->loan_id)->max('sequence') + 1])->save();

            return $locked;
        });
    }
}
