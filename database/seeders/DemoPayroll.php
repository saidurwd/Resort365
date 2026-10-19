<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Modules\HR\Contracts\EmployeeDirectory;
use Modules\HR\DTOs\EmployeeSummary;
use Modules\Payroll\Actions\SeedBangladeshPayroll;
use Modules\Payroll\Actions\SetEmployeeSalary;
use Modules\Payroll\Models\EmployeeSalary;
use Modules\Payroll\Models\SalaryStructure;

/**
 * Payroll demo (Step 6.4): the Bangladesh starter pack and a salary for every employee on the standard structure (the
 * casual one for laundry attendants and guards), the basic by designation, a conveyance allowance set for the managers, a
 * 10% raise from the start of the year for every seventh employee (so their history shows two records), and a few women and
 * senior taxpayers and one person with a disability, who have higher tax-free amounts.
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
}
