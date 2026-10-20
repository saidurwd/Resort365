<?php

namespace Modules\Payroll\Database\Factories\Concerns;

use App\Support\Tenancy\PropertyContext;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Payroll records point at employees and properties, which are HR's and Property's. Factories use an existing one of the
 * tenant or insert a minimal one by id, because Payroll may not use their models (tests only).
 */
trait ResolvesReferences
{
    protected function propertyId(): int
    {
        $current = app(PropertyContext::class)->currentId();

        if ($current !== null) {
            return $current;
        }

        $tenantId = app(TenantContext::class)->tenantOrFail(static::class)->id;
        $existing = DB::table('properties')->where('tenant_id', $tenantId)->orderBy('id')->value('id');

        return is_numeric($existing) ? (int) $existing : $this->insertProperty($tenantId);
    }

    protected function employeeId(): int
    {
        $tenantId = app(TenantContext::class)->tenantOrFail(static::class)->id;
        $existing = DB::table('employees')->where('tenant_id', $tenantId)->orderBy('id')->value('id');

        return is_numeric($existing) ? (int) $existing : $this->insertEmployee($tenantId);
    }

    /** A new employee every time, for records that may hold one employee only once. */
    protected function newEmployeeId(): int
    {
        return $this->insertEmployee(app(TenantContext::class)->tenantOrFail(static::class)->id);
    }

    private function insertProperty(int $tenantId): int
    {
        $now = now();
        DB::table('countries')->insertOrIgnore(['code' => 'BD', 'iso3' => 'BGD', 'numeric_code' => '050', 'name' => 'Bangladesh']);
        DB::table('currencies')->insertOrIgnore(['code' => 'BDT', 'name' => 'Bangladeshi Taka', 'symbol' => 'BDT', 'decimals' => 2]);
        DB::table('timezones')->insertOrIgnore(['name' => 'Asia/Dhaka', 'country_code' => 'BD', 'utc_offset' => '+06:00']);

        return DB::table('properties')->insertGetId([
            'tenant_id' => $tenantId, 'code' => 'P'.fake()->unique()->numberBetween(100, 99999), 'name' => fake()->city().' Resort', 'country_code' => 'BD', 'timezone' => 'Asia/Dhaka',
            'currency_code' => 'BDT', 'business_date' => $now->toDateString(), 'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function insertEmployee(int $tenantId): int
    {
        $now = now();
        $property = DB::table('properties')->where('tenant_id', $tenantId)->value('id') ?? $this->insertProperty($tenantId);
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
