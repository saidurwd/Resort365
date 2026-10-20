<?php

namespace Modules\Payroll\Services;

use Brick\Math\BigDecimal;
use Modules\Payroll\Contracts\PayrollLedgerFacts;
use Modules\Payroll\DTOs\LoanFact;
use Modules\Payroll\DTOs\PayrollPaymentFact;
use Modules\Payroll\DTOs\PayrollRunFact;
use Modules\Payroll\Enums\ComponentKind;
use Modules\Payroll\Enums\InstallmentStatus;
use Modules\Payroll\Enums\RunStatus;
use Modules\Payroll\Models\Loan;
use Modules\Payroll\Models\LoanInstallment;
use Modules\Payroll\Models\PayrollRun;

/**
 * Payroll's implementation of `PayrollLedgerFacts` (Step 6.5). It works across properties, as the posting is a system reaction.
 */
class PayrollLedgerFactsService implements PayrollLedgerFacts
{
    public function run(int $runId): ?PayrollRunFact
    {
        $run = PayrollRun::query()->with('payslips.lines')->find($runId);

        if (! $run instanceof PayrollRun || ! in_array($run->status, [RunStatus::Approved, RunStatus::Paid], true)) {
            return null;
        }

        $byDepartment = [];
        $sum = ['pfEmployee' => BigDecimal::zero(), 'pfEmployer' => BigDecimal::zero(), 'other' => BigDecimal::zero(), 'loans' => BigDecimal::zero(), 'tax' => BigDecimal::zero(), 'net' => BigDecimal::zero(), 'service' => BigDecimal::zero(), 'tips' => BigDecimal::zero()];

        foreach ($run->payslips as $slip) {
            $amounts = $slip->lines->groupBy('code')->map(fn ($lines) => $lines->reduce(fn (BigDecimal $carry, $line): BigDecimal => $carry->plus((string) $line->amount), BigDecimal::zero()));
            $loans = ($amounts['LOAN'] ?? BigDecimal::zero())->plus($amounts['ADVANCE'] ?? BigDecimal::zero());
            $pfEmployee = $amounts['PF_EMP'] ?? BigDecimal::zero();
            $employerOther = $slip->lines->filter(fn ($line): bool => $line->kind === ComponentKind::Employer && $line->code !== 'PF_ER')->reduce(fn (BigDecimal $carry, $line): BigDecimal => $carry->plus((string) $line->amount), BigDecimal::zero());
            $service = $amounts['SCSHARE'] ?? BigDecimal::zero();
            $tips = $amounts['TIPSHARE'] ?? BigDecimal::zero();
            $key = (string) ($slip->department_id ?? 0);
            $byDepartment[$key] = ($byDepartment[$key] ?? BigDecimal::zero())->plus((string) $slip->net)->plus((string) $slip->tax)->plus($pfEmployee)->plus($loans)->minus($service)->minus($tips);
            $sum['service'] = $sum['service']->plus($service);
            $sum['tips'] = $sum['tips']->plus($tips);
            $sum['pfEmployee'] = $sum['pfEmployee']->plus($pfEmployee);
            $sum['pfEmployer'] = $sum['pfEmployer']->plus($amounts['PF_ER'] ?? BigDecimal::zero());
            $sum['other'] = $sum['other']->plus($employerOther);
            $sum['loans'] = $sum['loans']->plus($loans);
            $sum['tax'] = $sum['tax']->plus((string) $slip->tax);
            $sum['net'] = $sum['net']->plus((string) $slip->net);
        }

        $expense = [];

        foreach ($byDepartment as $department => $amount) {
            $expense[] = ['department_id' => (int) $department > 0 ? (int) $department : null, 'amount' => (string) $amount->toScale(2)];
        }

        return new PayrollRunFact(
            $run->id, $run->run_no, $run->property_id, $run->period->endOfMonth()->toDateString(), $expense, (string) $sum['pfEmployer']->toScale(2), (string) $sum['other']->toScale(2), (string) $sum['net']->toScale(2),
            (string) $sum['tax']->toScale(2), (string) $sum['pfEmployee']->toScale(2), (string) $sum['loans']->toScale(2), (string) $sum['service']->toScale(2), (string) $sum['tips']->toScale(2),
        );
    }

    public function payment(int $runId): ?PayrollPaymentFact
    {
        $run = PayrollRun::query()->find($runId);

        return $run instanceof PayrollRun && $run->status === RunStatus::Paid && $run->paid_on !== null && $run->payment_method !== null
            ? new PayrollPaymentFact($run->id, $run->run_no, $run->property_id, $run->paid_on->toDateString(), (string) $run->net_total, $run->payment_method->value, $run->payment_reference) : null;
    }

    public function loan(int $loanId): ?LoanFact
    {
        $loan = Loan::query()->find($loanId);

        return $loan instanceof Loan ? new LoanFact($loan->id, $loan->loan_no, $loan->property_id, $loan->disbursed_on->toDateString(), (string) $loan->principal, $loan->payment_method->value, $loan->employee_id) : null;
    }

    public function waiver(int $installmentId): ?LoanFact
    {
        $installment = LoanInstallment::query()->with('loan')->find($installmentId);

        return $installment instanceof LoanInstallment && $installment->status === InstallmentStatus::Waived
            ? new LoanFact($installment->id, $installment->loan->loan_no, $installment->loan->property_id, ($installment->settled_at ?? now())->toDateString(), (string) $installment->amount, $installment->loan->payment_method->value, $installment->loan->employee_id) : null;
    }

    public function history(): array
    {
        return [
            'approved' => PayrollRun::query()->whereIn('status', [RunStatus::Approved->value, RunStatus::Paid->value])->orderBy('id')->pluck('id')->all(),
            'paid' => PayrollRun::query()->where('status', RunStatus::Paid->value)->orderBy('id')->pluck('id')->all(),
            'loans' => Loan::query()->orderBy('id')->pluck('id')->all(),
            'waivers' => LoanInstallment::query()->where('status', InstallmentStatus::Waived->value)->orderBy('id')->pluck('id')->all(),
        ];
    }
}
