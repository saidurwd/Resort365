<?php

namespace Modules\HR\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\HR\Models\LeaveType;

/**
 * @extends Factory<LeaveType>
 */
class LeaveTypeFactory extends Factory
{
    protected $model = LeaveType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['code' => 'L'.fake()->unique()->numerify('###'), 'name' => fake()->unique()->word().' leave', 'is_paid' => true, 'has_balance' => true, 'counts_non_working_days' => false, 'color' => 'info', 'is_active' => true];
    }
}
