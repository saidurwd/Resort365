<?php

namespace Modules\HR\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\HR\Database\Factories\Concerns\ResolvesReferences;
use Modules\HR\Enums\AttendanceSource;
use Modules\HR\Enums\AttendanceStatus;
use Modules\HR\Models\Attendance;
use Modules\HR\Models\Employee;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    use ResolvesReferences;

    protected $model = Attendance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['property_id' => $this->propertyId(), 'employee_id' => Employee::factory(), 'work_date' => now()->toDateString(), 'status' => AttendanceStatus::Present, 'source' => AttendanceSource::Manual, 'clock_in' => '09:00:00', 'clock_out' => '17:00:00', 'worked_minutes' => 480];
    }
}
