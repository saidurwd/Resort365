<?php

namespace Modules\Reservation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Reservation\Database\Factories\Concerns\ResolvesRoom;
use Modules\Reservation\Enums\LockType;
use Modules\Reservation\Models\InventoryLock;

/**
 * Locks of a room of the current property. Pass room_id (and stay_date) for a specific room-night.
 *
 * @extends Factory<InventoryLock>
 */
class InventoryLockFactory extends Factory
{
    use ResolvesRoom;

    protected $model = InventoryLock::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'room_id' => fn (array $attributes): int => $this->roomId((int) $attributes['property_id']),
            'stay_date' => fake()->unique()->dateTimeBetween('now', '+3 years')->format('Y-m-d'),
            'lock_type' => LockType::OutOfOrder,
            'note' => null,
        ];
    }
}
