<?php

namespace Modules\HR\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\HR\Enums\HistoryEvent;
use Modules\HR\Models\Employee;
use Modules\HR\Models\EmployeeHistory;

/**
 * @extends Factory<EmployeeHistory>
 */
class EmployeeHistoryFactory extends Factory
{
    protected $model = EmployeeHistory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['employee_id' => Employee::factory(), 'event' => HistoryEvent::Joined, 'effective_on' => now()->toDateString()];
    }
}
