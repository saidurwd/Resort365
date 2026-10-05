<?php

namespace Modules\Restaurant\Database\Factories\Concerns;

use App\Support\Tenancy\PropertyContext;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Restaurant records belong to a property, which is Property's model: factories use the current
 * property, else the tenant's first one, else insert a minimal one (tests only).
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
            'tenant_id' => $tenantId, 'code' => 'R'.fake()->unique()->numberBetween(100, 99999), 'name' => fake()->city().' Resort',
            'country_code' => 'BD', 'timezone' => 'Asia/Dhaka', 'currency_code' => 'BDT', 'business_date' => now()->toDateString(),
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * An outlet of the property (or a new one).
     */
    protected function outletId(int $propertyId): int
    {
        $existing = DB::table('outlets')->where('property_id', $propertyId)->orderBy('id')->value('id');

        return is_numeric($existing) ? (int) $existing : DB::table('outlets')->insertGetId([
            'tenant_id' => (int) DB::table('properties')->where('id', $propertyId)->value('tenant_id'), 'property_id' => $propertyId,
            'code' => 'O'.fake()->unique()->numberBetween(100, 99999), 'name' => 'Restaurant', 'type' => 'restaurant', 'bill_prefix' => 'R',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * A dining area of the outlet (or a new one).
     */
    protected function areaId(int $outletId): int
    {
        $existing = DB::table('dining_areas')->where('outlet_id', $outletId)->orderBy('id')->value('id');
        $outlet = DB::table('outlets')->where('id', $outletId)->first(['tenant_id', 'property_id']);

        return is_numeric($existing) ? (int) $existing : DB::table('dining_areas')->insertGetId([
            'tenant_id' => $outlet?->tenant_id, 'property_id' => $outlet?->property_id, 'outlet_id' => $outletId,
            'name' => 'Area '.fake()->unique()->numberBetween(1, 99999), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * A user of the property's tenant (a new one each time).
     */
    protected function userId(int $propertyId): int
    {
        return DB::table('users')->insertGetId([
            'tenant_id' => (int) DB::table('properties')->where('id', $propertyId)->value('tenant_id'), 'name' => fake()->name(), 'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'), 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * A terminal of the outlet (or a new one).
     */
    protected function terminalId(int $outletId): int
    {
        $outlet = DB::table('outlets')->where('id', $outletId)->first(['tenant_id', 'property_id']);
        $existing = DB::table('pos_terminals')->where('outlet_id', $outletId)->orderBy('id')->value('id');

        return is_numeric($existing) ? (int) $existing : DB::table('pos_terminals')->insertGetId([
            'tenant_id' => $outlet?->tenant_id, 'property_id' => $outlet?->property_id, 'outlet_id' => $outletId, 'name' => 'Tablet',
            'device_token' => hash('sha256', fake()->unique()->uuid()), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
