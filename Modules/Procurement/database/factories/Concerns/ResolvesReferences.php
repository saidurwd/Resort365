<?php

namespace Modules\Procurement\Database\Factories\Concerns;

use App\Support\Tenancy\PropertyContext;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Procurement records point at properties and items, which are other modules' models. Factories use the current
 * property, else the tenant's first, else insert a minimal one; the same for items (tests only).
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

    protected function itemId(): int
    {
        $tenantId = app(TenantContext::class)->tenantOrFail(static::class)->id;
        $existing = DB::table('items')->where('tenant_id', $tenantId)->orderBy('id')->value('id');

        if (is_numeric($existing)) {
            return (int) $existing;
        }

        $unit = DB::table('units')->where('tenant_id', $tenantId)->value('id') ?? DB::table('units')->insertGetId([
            'tenant_id' => $tenantId, 'code' => 'kg', 'name' => 'Kilogram', 'kind' => 'weight', 'factor' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $category = DB::table('item_categories')->where('tenant_id', $tenantId)->value('id') ?? DB::table('item_categories')->insertGetId([
            'tenant_id' => $tenantId, 'name' => 'Food', 'sort_order' => 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('items')->insertGetId([
            'tenant_id' => $tenantId, 'item_category_id' => $category, 'sku' => 'PF-'.fake()->unique()->numerify('#####'), 'name' => fake()->unique()->word().' '.fake()->numerify('###'),
            'base_unit_id' => $unit, 'is_stockable' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
