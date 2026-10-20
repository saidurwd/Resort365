<?php

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Payroll\Database\Factories\Concerns\ResolvesReferences;
use Modules\Payroll\Models\PoolParticipant;

/**
 * @extends Factory<PoolParticipant>
 */
class PoolParticipantFactory extends Factory
{
    use ResolvesReferences;

    protected $model = PoolParticipant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['employee_id' => $this->newEmployeeId(), 'is_eligible' => true, 'points' => '1.00'];
    }
}
