<?php

namespace Modules\Payroll\Actions;

use App\Support\Actions\Action;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\HR\Contracts\EmployeeDirectory;
use Modules\HR\Enums\EmployeeStatus;
use Modules\Payroll\Enums\InstallmentStatus;
use Modules\Payroll\Enums\LoanKind;
use Modules\Payroll\Enums\LoanStatus;
use Modules\Payroll\Enums\PayMethod;
use Modules\Payroll\Events\LoanDisbursed;
use Modules\Payroll\Exceptions\PayrollRuleViolated;
use Modules\Payroll\Models\Loan;
use Modules\Payroll\Models\LoanInstallment;
use Modules\Payroll\Services\Calculation\LoanSchedule;

/**
 * Pays an employee a loan or a salary advance (Step 6.5) and schedules its recovery: equal installments, one a month from the
 * first month, deducted by the payroll runs (a loan up to 36 installments, an advance up to 3). Fires `LoanDisbursed` for
 * Accounting. Not for someone who has left.
 */
class CreateLoan extends Action
{
    public function __construct(
        private readonly EmployeeDirectory $employees,
        private readonly DocumentNumbers $numbers,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @throws PayrollRuleViolated
     */
    public function handle(int $employeeId, LoanKind $kind, string $principal, int $installments, string $firstMonth, string $disbursedOn, PayMethod $method, ?string $reason, ?int $userId, ?string $today = null): Loan
    {
        $employee = $this->employees->find($employeeId) ?? throw new PayrollRuleViolated(__('That employee does not exist, or you cannot see them.'));
        $today ??= now()->toDateString();
        $first = CarbonImmutable::parse($firstMonth)->startOfMonth();

        if ($employee->status === EmployeeStatus::Exited) {
            throw new PayrollRuleViolated(__(':name has left, so a loan cannot be given.', ['name' => $employee->name]));
        }

        if (! is_numeric($principal) || ! BigDecimal::of($principal)->isPositive()) {
            throw new PayrollRuleViolated(__('Enter the amount to lend.'));
        }

        $most = $kind === LoanKind::Advance ? 3 : 36;

        if ($installments < 1 || $installments > $most) {
            throw new PayrollRuleViolated(__('A :kind is repaid in 1 to :most installments.', ['kind' => mb_strtolower($kind->label()), 'most' => $most]));
        }

        if ($first->lt(CarbonImmutable::parse($disbursedOn)->startOfMonth())) {
            throw new PayrollRuleViolated(__('Recovery cannot start before the month the money is paid.'));
        }

        if ($disbursedOn > $today) {
            throw new PayrollRuleViolated(__('The payment cannot be dated in the future.'));
        }

        return $this->transaction(function () use ($employee, $kind, $principal, $installments, $first, $disbursedOn, $method, $reason, $userId): Loan {
            $loan = Loan::query()->create([
                'property_id' => $employee->propertyId, 'loan_no' => $this->numbers->next('loan', $employee->propertyId), 'employee_id' => $employee->id, 'kind' => $kind, 'principal' => number_format((float) $principal, 2, '.', ''),
                'installments_count' => $installments, 'first_month' => $first->toDateString(), 'disbursed_on' => $disbursedOn, 'payment_method' => $method,
                'reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null, 'status' => LoanStatus::Active, 'created_by' => $userId,
            ]);

            foreach (LoanSchedule::build($principal, $installments, $first->toDateString()) as $row) {
                LoanInstallment::query()->create(['loan_id' => $loan->id, 'sequence' => $row['sequence'], 'due_month' => $row['due_month'], 'amount' => $row['amount'], 'status' => InstallmentStatus::Scheduled]);
            }

            event(new LoanDisbursed($this->tenant->tenantOrFail(self::class)->id, $loan->id, $loan->property_id));

            return $loan->load('installments');
        }, attempts: 3);
    }
}
