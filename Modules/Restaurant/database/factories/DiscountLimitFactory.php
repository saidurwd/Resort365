<?php

namespace Modules\Restaurant\Database\Factories;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Modules\Restaurant\Models\DiscountLimit;

/**
 * @extends Factory<DiscountLimit>
 */
class DiscountLimitFactory extends Factory
{
    protected $model = DiscountLimit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Roles are IAM's model: a custom role of the current tenant, inserted directly (tests only).
            'role_id' => fn (): int => DB::table('roles')->insertGetId([
                'tenant_id' => app(TenantContext::class)->tenantOrFail(DiscountLimit::class)->id, 'name' => 'Role '.fake()->unique()->numberBetween(1, 999999),
                'guard_name' => 'web', 'is_system' => false, 'created_at' => now(), 'updated_at' => now(),
            ]),
            'max_percent' => '10.00',
        ];
    }
}
