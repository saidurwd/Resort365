<?php

namespace Modules\Billing\Database\Factories\Concerns;

use App\Support\Tenancy\PropertyContext;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Billing records belong to a property, which is another module's model. Factories use the current
 * property, else the tenant's first property, else insert a minimal one (tests only).
 */
trait ResolvesProperty
{
    protected function propertyId(): int
    {
        $current = app(PropertyContext::class)->currentId();

        if ($current !== null) {
            return $current;
        }

        // Fails closed like BelongsToTenant (TenantContextMissing) when there is no tenant.
        $tenantId = app(TenantContext::class)->tenantOrFail(static::class)->id;
        $existing = DB::table('properties')->where('tenant_id', $tenantId)->orderBy('id')->value('id');

        if (is_numeric($existing)) {
            return (int) $existing;
        }

        DB::table('countries')->insertOrIgnore(['code' => 'BD', 'iso3' => 'BGD', 'numeric_code' => '050', 'name' => 'Bangladesh']);
        DB::table('currencies')->insertOrIgnore(['code' => 'BDT', 'name' => 'Bangladeshi Taka', 'symbol' => 'BDT', 'decimals' => 2]);
        DB::table('timezones')->insertOrIgnore(['name' => 'Asia/Dhaka', 'country_code' => 'BD', 'utc_offset' => '+06:00']);

        return DB::table('properties')->insertGetId([
            'tenant_id' => $tenantId, 'code' => 'B'.fake()->unique()->numberBetween(100, 99999), 'name' => fake()->city().' Resort',
            'country_code' => 'BD', 'timezone' => 'Asia/Dhaka', 'currency_code' => 'BDT', 'business_date' => now()->toDateString(),
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
