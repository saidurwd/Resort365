<?php

namespace Modules\Restaurant\Database\Factories\Concerns;

use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Restaurant records point at inventory items and stores by id, which are another module's models. Factories use the
 * tenant's first one, else insert a minimal one (tests only).
 */
trait ResolvesInventoryItem
{
    protected function inventoryItemId(): int
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
            'tenant_id' => $tenantId, 'item_category_id' => $category, 'sku' => 'RF-'.fake()->unique()->numerify('#####'), 'name' => fake()->unique()->word().' '.fake()->numerify('###'),
            'base_unit_id' => $unit, 'is_stockable' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function inventoryStoreId(int $propertyId): int
    {
        $tenantId = app(TenantContext::class)->tenantOrFail(static::class)->id;
        $existing = DB::table('stores')->where('tenant_id', $tenantId)->where('property_id', $propertyId)->orderBy('id')->value('id');

        if (is_numeric($existing)) {
            return (int) $existing;
        }

        return DB::table('stores')->insertGetId([
            'tenant_id' => $tenantId, 'property_id' => $propertyId, 'code' => 'K'.fake()->unique()->numerify('###'), 'name' => 'Kitchen store', 'kind' => 'kitchen', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
