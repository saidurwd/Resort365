<?php

namespace Modules\Housekeeping\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Housekeeping\Database\Factories\Concerns\ResolvesRoom;
use Modules\Housekeeping\Enums\TaskStatus;
use Modules\Housekeeping\Enums\TaskType;
use Modules\Housekeeping\Models\HousekeepingTask;

/**
 * @extends Factory<HousekeepingTask>
 */
class HousekeepingTaskFactory extends Factory
{
    use ResolvesRoom;

    protected $model = HousekeepingTask::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'room_id' => fn (array $attributes): int => $this->roomId((int) $attributes['property_id']),
            'business_date' => now()->subDays(fake()->unique()->numberBetween(30, 3000))->toDateString(),
            'type' => TaskType::Departure,
            'status' => TaskStatus::Pending,
        ];
    }
}
