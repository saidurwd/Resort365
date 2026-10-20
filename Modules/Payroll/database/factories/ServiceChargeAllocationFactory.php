<?php

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Payroll\Database\Factories\Concerns\ResolvesReferences;
use Modules\Payroll\Models\ServiceChargeAllocation;
use Modules\Payroll\Models\ServiceChargePool;

/**
 * @extends Factory<ServiceChargeAllocation>
 */
class ServiceChargeAllocationFactory extends Factory
{
    use ResolvesReferences;

    protected $model = ServiceChargeAllocation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_charge_pool_id' => ServiceChargePool::factory(), 'employee_id' => $this->employeeId(), 'employee_no' => 'EMP-'.fake()->unique()->numerify('####'), 'employee_name' => fake()->name(),
            'weight' => '1.0000', 'amount' => '1000.00',
        ];
    }
}
