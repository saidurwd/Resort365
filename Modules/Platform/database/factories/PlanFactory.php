<?php

namespace Modules\Platform\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Platform\Models\Plan;
use Modules\Platform\Services\ModuleCatalog;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'plan-'.fake()->unique()->numberBetween(1000, 999999), 'name' => fake()->unique()->words(2, true), 'description' => null, 'monthly_price' => '49.00', 'yearly_price' => '490.00', 'currency_code' => 'USD',
            'max_properties' => null, 'max_rooms' => null, 'max_users' => null, 'storage_gb' => null, 'modules' => ModuleCatalog::aliases(), 'api_access' => false, 'is_active' => true, 'sort_order' => 0,
        ];
    }
}
