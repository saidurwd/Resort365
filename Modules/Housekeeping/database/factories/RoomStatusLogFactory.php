<?php

namespace Modules\Housekeeping\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Housekeeping\Database\Factories\Concerns\ResolvesRoom;
use Modules\Housekeeping\Models\RoomStatusLog;
use Modules\Property\Enums\HousekeepingStatus;

/**
 * @extends Factory<RoomStatusLog>
 */
class RoomStatusLogFactory extends Factory
{
    use ResolvesRoom;

    protected $model = RoomStatusLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'room_id' => fn (array $attributes): int => $this->roomId((int) $attributes['property_id']),
            'from_status' => HousekeepingStatus::Dirty,
            'to_status' => HousekeepingStatus::Clean,
            'reason' => 'Cleaned after check-out',
        ];
    }
}
