<?php

namespace Modules\Housekeeping\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Housekeeping\Database\Factories\Concerns\ResolvesRoom;
use Modules\Housekeeping\Enums\WorkOrderCategory;
use Modules\Housekeeping\Models\MaintenanceSchedule;

/**
 * @extends Factory<MaintenanceSchedule>
 */
class MaintenanceScheduleFactory extends Factory
{
    use ResolvesRoom;

    protected $model = MaintenanceSchedule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'title' => 'AC servicing',
            'category' => WorkOrderCategory::AirConditioning,
            'location' => 'All rooms',
            'interval_days' => 90,
            'next_due_on' => now()->addDays(30)->toDateString(),
            'is_active' => true,
        ];
    }
}
