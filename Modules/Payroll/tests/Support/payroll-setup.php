<?php

use Modules\HR\Models\Employee;
use Modules\IAM\Models\User;
use Modules\Payroll\Actions\SeedBangladeshPayroll;
use Modules\Payroll\Actions\SetEmployeeSalary;
use Modules\Payroll\Enums\RunStatus;
use Modules\Payroll\Models\EmployeeSalary;
use Modules\Payroll\Models\SalaryStructure;

require_once __DIR__.'/../../../HR/tests/Support/hr-setup.php';
require_once __DIR__.'/payroll-fixtures.php';

/** Loads the Bangladesh starter pack into the test tenant. */
function pyPrepare(): void
{
    booking(fn (): array => app(SeedBangladeshPayroll::class)->handle());
}

function pyStructure(string $code = 'STD'): SalaryStructure
{
    return booking(fn (): SalaryStructure => SalaryStructure::query()->where('code', $code)->firstOrFail());
}

/**
 * Gives the employee a salary from a date on the starter structure.
 *
 * @param  array<string, mixed>  $overrides
 */
function pySalary(Employee $employee, string $basic = '25000', string $from = '2025-08-01', array $overrides = []): EmployeeSalary
{
    return booking(fn (): EmployeeSalary => app(SetEmployeeSalary::class)->handle($employee->id, [
        'salary_structure_id' => pyStructure()->id, 'effective_from' => $from, 'basic' => $basic, 'tax_category' => 'general', 'tax_area' => 'metro', ...$overrides,
    ], 1));
}

use App\Support\Authorization\DefaultRole;
use Modules\Core\Actions\SeedApprovalWorkflows;
use Modules\Core\Contracts\ApprovalEngine;
use Modules\HR\Actions\RecordAttendance;
use Modules\Payroll\Actions\CalculatePayroll;
use Modules\Payroll\Actions\CreateLoan;
use Modules\Payroll\Actions\CreatePayrollRun;
use Modules\Payroll\Actions\SubmitPayrollRun;
use Modules\Payroll\Enums\LoanKind;
use Modules\Payroll\Enums\PayMethod;
use Modules\Payroll\Models\Loan;
use Modules\Payroll\Models\PayrollRun;

/** Starter pack and the approval workflows (the payroll_run workflow among them). */
function prPrepare(): void
{
    pyPrepare();
    booking(fn () => app(SeedApprovalWorkflows::class)->handle());
}

function prUser(DefaultRole $role = DefaultRole::PayrollOfficer): User
{
    return tenantUserAs(tenant('sunrise'), $role);
}

/**
 * The March 2026 scenario of the worked examples:
 * - Rahim (EMP-0001), basic 25,000 (city corporation), rostered 2–6 March on the day shift: 10 hours on the 2nd (2 hours of overtime) and a missing day on the 5th;
 * - New (EMP-0002), basic 20,000, joined on 16 March (16 of 31 days);
 * - Zed (EMP-0003), no salary.
 *
 * @return array{0: Employee, 1: Employee, 2: Employee}
 */
function prMarch(): array
{
    $rahim = hrEmployee(['first_name' => 'Rahim', 'bank_name' => 'City Bank', 'bank_branch' => 'Main', 'bank_account_name' => 'Rahim Uddin', 'bank_account_no' => '1101-555', 'email' => 'rahim@example.test']);
    $new = hrEmployee(['first_name' => 'New', 'joined_on' => '2026-03-16']);
    $zed = hrEmployee(['first_name' => 'Zed']);
    pySalary($rahim, '25000', '2025-08-01');
    pySalary($new, '20000', '2026-03-16', ['tax_area' => 'other']);
    $shift = hrShift('DAY', '09:00', '17:00', ['break_minutes' => 30, 'grace_minutes' => 10, 'overtime_after_minutes' => 15]);
    hrRoster([$rahim->id], '2026-03-02', '2026-03-06', $shift->id);

    foreach ([['2026-03-02', '09:00', '19:00'], ['2026-03-03', '09:00', '17:00'], ['2026-03-04', '09:00', '17:00'], ['2026-03-06', '09:00', '17:00']] as [$date, $in, $out]) {
        booking(fn () => app(RecordAttendance::class)->handle(hrFresh($rahim), $date, $in, $out, null, null, 1));
    }

    return [$rahim, $new, $zed];
}

function prRun(string $month = '2026-03', bool $calculate = true): PayrollRun
{
    $run = booking(fn (): PayrollRun => app(CreatePayrollRun::class)->handle(bookingIds()['property'], $month, [], null, prUser()->id));

    return $calculate ? booking(fn (): PayrollRun => app(CalculatePayroll::class)->handle($run)) : $run;
}

function prFresh(PayrollRun $run): PayrollRun
{
    return booking(fn (): PayrollRun => PayrollRun::query()->findOrFail($run->id));
}

/** Sends a calculated run for approval and has the accountant approve it. */
function prApprove(PayrollRun $run): PayrollRun
{
    $submitted = booking(fn (): PayrollRun => app(SubmitPayrollRun::class)->handle(prFresh($run), prUser()->id));

    if ($submitted->status === RunStatus::Pending) {
        booking(fn () => app(ApprovalEngine::class)->approve((int) $submitted->approval_request_id, prUser(DefaultRole::Accountant)->id));
    }

    return prFresh($run);
}

function prLoan(Employee $employee, string $principal = '6000', int $installments = 3, LoanKind $kind = LoanKind::Loan, string $firstMonth = '2026-03-01', string $disbursed = '2026-02-20'): Loan
{
    return booking(fn (): Loan => app(CreateLoan::class)->handle($employee->id, $kind, $principal, $installments, $firstMonth, $disbursed, PayMethod::Cash, 'Medical', 1));
}
