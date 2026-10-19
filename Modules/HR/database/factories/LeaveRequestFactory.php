<?php

namespace Modules\HR\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\HR\Database\Factories\Concerns\ResolvesReferences;
use Modules\HR\Enums\LeaveStatus;
use Modules\HR\Models\Employee;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Models\LeaveType;

/**
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    use ResolvesReferences;

    protected $model = LeaveRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = now()->addDays(10)->toDateString();

        return [
            'property_id' => $this->propertyId(), 'request_no' => 'LV-'.fake()->unique()->numerify('####-#####'), 'employee_id' => Employee::factory(), 'leave_type_id' => LeaveType::factory(),
            'from_date' => $date, 'to_date' => $date, 'days' => '1.0', 'charged_dates' => [$date], 'days_by_year' => [(string) now()->addDays(10)->year => '1.0'], 'status' => LeaveStatus::Pending, 'requested_by' => 1,
        ];
    }
}
