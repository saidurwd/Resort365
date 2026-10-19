<?php

namespace Modules\HR\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\HR\Models\Employee;
use Modules\HR\Models\LeaveBalance;
use Modules\HR\Models\LeaveType;

/**
 * @extends Factory<LeaveBalance>
 */
class LeaveBalanceFactory extends Factory
{
    protected $model = LeaveBalance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['employee_id' => Employee::factory(), 'leave_type_id' => LeaveType::factory(), 'year' => (int) now()->format('Y'), 'entitled' => '10.0', 'carried_forward' => '0.0', 'adjusted' => '0.0', 'used' => '0.0', 'pending' => '0.0'];
    }
}
