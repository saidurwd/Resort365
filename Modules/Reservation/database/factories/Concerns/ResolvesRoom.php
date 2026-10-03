<?php

namespace Modules\Reservation\Database\Factories\Concerns;

use App\Support\Tenancy\PropertyContext;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Locks belong to a property's room, which are another module's models. Factories use the current
 * property (or the tenant's first, or a new minimal one) and a room of it (or a new minimal one).
 * Tests only.
 */
trait ResolvesRoom
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
            'tenant_id' => $tenantId, 'code' => 'L'.fake()->unique()->numberBetween(100, 99999), 'name' => fake()->city().' Resort',
            'country_code' => 'BD', 'timezone' => 'Asia/Dhaka', 'currency_code' => 'BDT', 'business_date' => now()->toDateString(),
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function roomId(int $propertyId): int
    {
        $existing = DB::table('rooms')->where('property_id', $propertyId)->whereNull('deleted_at')->orderBy('id')->value('id');

        if (is_numeric($existing)) {
            return (int) $existing;
        }

        $tenantId = (int) DB::table('properties')->where('id', $propertyId)->value('tenant_id');
        $now = now();
        $base = ['tenant_id' => $tenantId, 'property_id' => $propertyId, 'created_at' => $now, 'updated_at' => $now];
        $cottageType = DB::table('cottage_types')->insertGetId([...$base, 'code' => 'T'.fake()->unique()->numberBetween(1, 99999), 'name' => 'Cottage', 'max_occupancy' => 2]);
        $roomType = DB::table('room_types')->insertGetId([...$base, 'code' => 'R'.fake()->unique()->numberBetween(1, 99999), 'name' => 'Room',
            'base_occupancy' => 2, 'max_adults' => 2, 'max_children' => 0, 'max_occupancy' => 2]);
        $cottage = DB::table('cottages')->insertGetId([...$base, 'cottage_type_id' => $cottageType, 'code' => 'C'.fake()->unique()->numberBetween(1, 99999), 'name' => 'Cottage']);

        return DB::table('rooms')->insertGetId([...$base, 'cottage_id' => $cottage, 'room_type_id' => $roomType, 'number' => (string) fake()->unique()->numberBetween(1000, 99999)]);
    }

    protected function cottageIdOf(int $roomId): int
    {
        return (int) DB::table('rooms')->where('id', $roomId)->value('cottage_id');
    }

    protected function guestId(int $propertyId): int
    {
        $tenantId = (int) DB::table('properties')->where('id', $propertyId)->value('tenant_id');
        $existing = DB::table('guests')->where('tenant_id', $tenantId)->orderBy('id')->value('id');

        return is_numeric($existing) ? (int) $existing : DB::table('guests')->insertGetId([
            'tenant_id' => $tenantId, 'first_name' => fake()->firstName(), 'last_name' => fake()->lastName(), 'vip_level' => 'none',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function ratePlanId(int $propertyId): int
    {
        $existing = DB::table('rate_plans')->where('property_id', $propertyId)->orderBy('id')->value('id');

        return is_numeric($existing) ? (int) $existing : DB::table('rate_plans')->insertGetId([
            'tenant_id' => (int) DB::table('properties')->where('id', $propertyId)->value('tenant_id'), 'property_id' => $propertyId,
            'code' => 'RO'.fake()->unique()->numberBetween(1, 99999), 'name' => 'Room Only', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
