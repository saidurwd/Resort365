<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Modules\Core\Contracts\ApprovalEngine;
use Modules\HR\Contracts\EmployeeDirectory;
use Modules\HR\DTOs\EmployeeSummary;
use Modules\IAM\Models\User;
use Modules\Payroll\Actions\CalculatePayroll;
use Modules\Payroll\Actions\CreateLoan;
use Modules\Payroll\Actions\CreatePayrollRun;
use Modules\Payroll\Actions\FinalisePool;
use Modules\Payroll\Actions\PayPayrollRun;
use Modules\Payroll\Actions\PreparePool;
use Modules\Payroll\Actions\SavePoolParticipants;
use Modules\Payroll\Actions\SeedBangladeshPayroll;
use Modules\Payroll\Actions\SetEmployeeSalary;
use Modules\Payroll\Actions\SubmitPayrollRun;
use Modules\Payroll\Enums\LoanKind;
use Modules\Payroll\Enums\PayMethod;
use Modules\Payroll\Enums\PoolKind;
use Modules\Payroll\Enums\PoolRule;
use Modules\Payroll\Enums\RunStatus;
use Modules\Payroll\Models\EmployeeSalary;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\SalaryStructure;

/**
 * Payroll demo (Step 6.4): the Bangladesh starter pack and a salary for every employee on the standard structure (the
 * casual one for laundry attendants and guards), the basic by designation, a conveyance allowance set for the managers, a
 * 10% raise from the start of the year for every seventh employee (so their history shows two records), and a few women and
 * senior taxpayers and one person with a disability, who have higher tax-free amounts. Step 6.5 (`runs`): two staff members
 * with a loan and a salary advance, last month's payroll calculated from the imported attendance, approved by the accountant
 * (and the General Manager, for a large total), paid and posted, and this month's run opened as a draft. Step 6.6: the General
 * Manager does not share in the service charge and tips, the other department heads hold two points, each month's pools are
 * worked out from the ledger and sent to payroll, and this month's run is calculated with the shares in.
 */
final class DemoPayroll
{
    /** @var array<string, int> designation => monthly basic */
    private const array BASIC = [
        'General Manager' => 150000, 'Executive Chef' => 85000, 'F&B Manager' => 80000, 'Front Office Manager' => 75000, 'HR Manager' => 70000, 'Sales Manager' => 65000, 'Accountant' => 55000,
        'Sous Chef' => 45000, 'Housekeeping Supervisor' => 40000, 'Security Supervisor' => 38000, 'Executive Assistant' => 35000, 'Procurement Officer' => 32000, 'Payroll Officer' => 32000,
        'Maintenance Technician' => 28000, 'Storekeeper' => 28000, 'Senior Receptionist' => 28000, 'Sales Executive' => 26000, 'Electrician' => 24000, 'Plumber' => 24000, 'Receptionist' => 22000,
        'Reservations Agent' => 22000, 'Cashier' => 20000, 'Bartender' => 20000, 'Accounts Assistant' => 20000, 'Waiter' => 17000, 'Bellman' => 15000, 'Room Attendant' => 14000,
        'Commis Chef' => 14000, 'Store Assistant' => 14000, 'Laundry Attendant' => 13000, 'Security Guard' => 13000,
    ];

    /** @var list<string> */
    private const array MANAGERS = ['General Manager', 'Executive Chef', 'F&B Manager', 'Front Office Manager', 'HR Manager', 'Sales Manager'];

    public static function seed(Tenant $tenant, int $propertyId): void
    {
        app(TenantContext::class)->run($tenant, function () use ($propertyId): void {
            app(SeedBangladeshPayroll::class)->handle();

            if (EmployeeSalary::query()->exists()) {
                return;
            }

            $structures = SalaryStructure::query()->pluck('id', 'code');
            $employees = app(EmployeeDirectory::class)->forProperty($propertyId);
            $startOfYear = CarbonImmutable::now()->startOfYear()->toDateString();

            foreach ($employees as $index => $employee) {
                /** @var EmployeeSummary $employee */
                $basic = self::BASIC[$employee->designation] ?? 15000;
                $casual = in_array($employee->designation, ['Laundry Attendant', 'Security Guard'], true);
                $manager = in_array($employee->designation, self::MANAGERS, true);
                $data = [
                    'salary_structure_id' => $structures[$casual ? 'CASUAL' : 'STD'], 'effective_from' => '2025-08-01', 'basic' => $basic,
                    'tax_category' => $index === 3 ? 'disabled' : ($index % 5 === 0 ? 'women_senior' : 'general'), 'tax_area' => $manager ? 'city' : 'other',
                    'amounts' => $manager ? ['CONV' => $employee->designation === 'General Manager' ? 5000 : 4000] : null,
                ];
                app(SetEmployeeSalary::class)->handle($employee->id, $data, null);

                if ($index % 7 === 6 && CarbonImmutable::now()->gt($startOfYear)) {
                    app(SetEmployeeSalary::class)->handle($employee->id, [...$data, 'effective_from' => $startOfYear, 'basic' => round($basic * 1.1, -2), 'notes' => 'Annual increment'], null);
                }
            }
        });
    }

    /**
     * Last month's payroll through to payment, two staff loans, and this month's empty run. Run after Accounting, so the
     * entries are posted as the run is approved and paid.
     */
    public static function runs(Tenant $tenant, int $propertyId, string $domain): void
    {
        app(TenantContext::class)->run($tenant, function () use ($propertyId, $domain): void {
            if (PayrollRun::query()->exists() || ! EmployeeSalary::query()->exists()) {
                return;
            }

            $user = fn (string $mailbox): int => (int) User::query()->where('email', $mailbox.'@'.$domain)->value('id');
            $today = CarbonImmutable::today();
            $last = $today->subMonthNoOverflow()->startOfMonth();
            $employees = app(EmployeeDirectory::class)->forProperty($propertyId);

            if (count($employees) > 12) {
                app(CreateLoan::class)->handle($employees[8]->id, LoanKind::Loan, '60000', 12, $last->format('Y-m'), $last->toDateString(), PayMethod::BankTransfer, 'Medical expenses', $user('payroll'));
                app(CreateLoan::class)->handle($employees[11]->id, LoanKind::Advance, '9000', 3, $last->format('Y-m'), $last->toDateString(), PayMethod::Cash, 'Salary advance', $user('payroll'));
            }

            self::participants($employees);
            self::pools($propertyId, $last->format('Y-m'), $user('payroll'));

            $run = app(CreatePayrollRun::class)->handle($propertyId, $last->format('Y-m'), [], null, $user('payroll'));
            $run = app(CalculatePayroll::class)->handle($run);
            $run = app(SubmitPayrollRun::class)->handle($run, $user('payroll'));

            foreach (['accountant', 'gm'] as $approver) {
                if (PayrollRun::query()->findOrFail($run->id)->status === RunStatus::Pending) {
                    app(ApprovalEngine::class)->approve((int) $run->approval_request_id, $user($approver));
                }
            }

            app(PayPayrollRun::class)->handle(PayrollRun::query()->findOrFail($run->id), PayMethod::BankTransfer, $today->toDateString(), 'BEFTN-'.$last->format('Ym'), $user('accountant'));

            self::pools($propertyId, $today->format('Y-m'), $user('payroll'));
            app(CalculatePayroll::class)->handle(app(CreatePayrollRun::class)->handle($propertyId, $today->format('Y-m'), [], null, $user('payroll')));
        });
    }

    /**
     * @param  list<EmployeeSummary>  $employees
     */
    private static function participants(array $employees): void
    {
        $rows = [];

        foreach ($employees as $employee) {
            if ($employee->designation === 'General Manager') {
                $rows[] = ['employee_id' => $employee->id, 'eligible' => false, 'points' => '1'];
            } elseif (in_array($employee->designation, self::MANAGERS, true)) {
                $rows[] = ['employee_id' => $employee->id, 'eligible' => true, 'points' => '2'];
            }
        }

        app(SavePoolParticipants::class)->handle($rows);
    }

    /** A month's service charge and tips pools, worked out from the ledger and sent to payroll when there is something to share. */
    private static function pools(int $propertyId, string $month, int $userId): void
    {
        foreach ([PoolKind::ServiceCharge, PoolKind::Tips] as $kind) {
            $pool = app(PreparePool::class)->handle($propertyId, $month, $kind, PoolRule::Points, null, $userId);

            if ($pool->allocations()->exists()) {
                app(FinalisePool::class)->handle($pool, $userId);
            }
        }
    }
}
