<?php

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Payroll\Database\Factories\Concerns\ResolvesReferences;
use Modules\Payroll\Enums\PoolKind;
use Modules\Payroll\Enums\PoolRule;
use Modules\Payroll\Enums\PoolStatus;
use Modules\Payroll\Models\ServiceChargePool;

/**
 * @extends Factory<ServiceChargePool>
 */
class ServiceChargePoolFactory extends Factory
{
    use ResolvesReferences;

    protected $model = ServiceChargePool::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(), 'kind' => PoolKind::ServiceCharge, 'period' => now()->startOfMonth()->subMonths(fake()->unique()->numberBetween(1, 5000))->toDateString(), 'rule' => PoolRule::Points, 'collected' => '100000.00', 'staff_percent' => '100.00',
            'distributable' => '100000.00', 'employees_count' => 0, 'status' => PoolStatus::Calculated,
        ];
    }
}
