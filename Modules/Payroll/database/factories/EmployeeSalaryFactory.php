<?php

namespace Modules\Payroll\Database\Factories;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Modules\Payroll\Models\EmployeeSalary;
use Modules\Payroll\Models\SalaryStructure;

/**
 * Employees belong to HR, so the factory takes an existing employee of the tenant or inserts a minimal one (tests only).
 *
 * @extends Factory<EmployeeSalary>
 */
class EmployeeSalaryFactory extends Factory
{
    protected $model = EmployeeSalary::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['employee_id' => 0, 'salary_structure_id' => SalaryStructure::factory(), 'effective_from' => fake()->unique()->dateTimeBetween('-9 years', '-1 year')->format('Y-m-d'), 'basic' => '25000.00', 'tax_category' => 'general', 'tax_area' => 'other'];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (EmployeeSalary $salary): void {
            if ($salary->employee_id === 0) {
                $tenantId = app(TenantContext::class)->tenantOrFail(self::class)->id;
                $existing = DB::table('employees')->where('tenant_id', $tenantId)->orderBy('id')->value('id');
                $salary->employee_id = is_numeric($existing) ? (int) $existing : $this->insertEmployee($tenantId);
            }
        });
    }

    /**
     * A minimal employee (HR's tables, inserted by id because Payroll may not use HR's models).
     */
    private function insertEmployee(int $tenantId): int
    {
        $now = now();
        DB::table('countries')->insertOrIgnore(['code' => 'BD', 'iso3' => 'BGD', 'numeric_code' => '050', 'name' => 'Bangladesh']);
        DB::table('currencies')->insertOrIgnore(['code' => 'BDT', 'name' => 'Bangladeshi Taka', 'symbol' => 'BDT', 'decimals' => 2]);
        DB::table('timezones')->insertOrIgnore(['name' => 'Asia/Dhaka', 'country_code' => 'BD', 'utc_offset' => '+06:00']);
        $property = DB::table('properties')->where('tenant_id', $tenantId)->value('id') ?? DB::table('properties')->insertGetId([
            'tenant_id' => $tenantId, 'code' => 'P'.fake()->unique()->numberBetween(100, 99999), 'name' => fake()->city().' Resort', 'country_code' => 'BD', 'timezone' => 'Asia/Dhaka',
            'currency_code' => 'BDT', 'business_date' => $now->toDateString(), 'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $department = DB::table('departments')->where('tenant_id', $tenantId)->value('id') ?? DB::table('departments')->insertGetId([
            'tenant_id' => $tenantId, 'code' => 'D'.fake()->unique()->numberBetween(100, 99999), 'name' => 'Department', 'is_active' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $designation = DB::table('designations')->where('tenant_id', $tenantId)->value('id') ?? DB::table('designations')->insertGetId([
            'tenant_id' => $tenantId, 'name' => 'Staff '.fake()->unique()->numberBetween(1, 99999), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);

        return DB::table('employees')->insertGetId([
            'tenant_id' => $tenantId, 'property_id' => $property, 'employee_no' => 'EMP-'.fake()->unique()->numerify('#####'), 'first_name' => fake()->firstName(), 'department_id' => $department,
            'designation_id' => $designation, 'employment_type' => 'permanent', 'joined_on' => $now->copy()->subYear()->toDateString(), 'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
        ]);
    }
}
