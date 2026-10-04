<?php

namespace Modules\Billing\Database\Factories\Concerns;

use App\Support\Tenancy\PropertyContext;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Billing records belong to a property (and sometimes a reservation), which are other modules'
 * models. Factories use the current property, else the tenant's first property, else insert a
 * minimal one; reservations likewise (tests only).
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

    /**
     * @param  bool  $new  always insert one (e.g. for rows unique per reservation)
     */
    protected function reservationId(int $propertyId, bool $new = false): int
    {
        $existing = $new ? null : DB::table('reservations')->where('property_id', $propertyId)->orderBy('id')->value('id');

        if (is_numeric($existing)) {
            return (int) $existing;
        }

        $tenantId = (int) DB::table('properties')->where('id', $propertyId)->value('tenant_id');
        $now = now();
        $guestId = DB::table('guests')->where('tenant_id', $tenantId)->orderBy('id')->value('id') ?? DB::table('guests')->insertGetId([
            'tenant_id' => $tenantId, 'first_name' => fake()->firstName(), 'last_name' => fake()->lastName(), 'vip_level' => 'none', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $planId = DB::table('rate_plans')->where('property_id', $propertyId)->orderBy('id')->value('id') ?? DB::table('rate_plans')->insertGetId([
            'tenant_id' => $tenantId, 'property_id' => $propertyId, 'code' => 'BRO'.fake()->unique()->numberBetween(1, 99999), 'name' => 'Room Only',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        return DB::table('reservations')->insertGetId([
            'tenant_id' => $tenantId, 'property_id' => $propertyId, 'code' => 'RSV-B-'.fake()->unique()->numberBetween(10000, 999999),
            'primary_guest_id' => $guestId, 'rate_plan_id' => $planId, 'check_in' => $now->copy()->addWeek()->toDateString(),
            'check_out' => $now->copy()->addWeek()->addDays(2)->toDateString(), 'adults' => 2, 'currency_code' => 'BDT', 'subtotal' => '12000.00',
            'grand_total' => '15180.00', 'balance_due' => '15180.00', 'created_at' => $now, 'updated_at' => $now,
        ]);
    }
}
