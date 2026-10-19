<?php

namespace Modules\HR\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\HR\Enums\LeaveAccrual;
use Modules\HR\Models\LeavePolicy;
use Modules\HR\Models\LeaveType;

/**
 * @extends Factory<LeavePolicy>
 */
class LeavePolicyFactory extends Factory
{
    protected $model = LeavePolicy::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['leave_type_id' => LeaveType::factory(), 'annual_days' => '10.0', 'accrual' => LeaveAccrual::Yearly, 'carry_forward_max' => '0.0', 'min_service_months' => 0];
    }
}
