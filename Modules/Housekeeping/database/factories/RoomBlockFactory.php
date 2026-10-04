<?php

namespace Modules\Housekeeping\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Housekeeping\Database\Factories\Concerns\ResolvesRoom;
use Modules\Housekeeping\Enums\BlockStatus;
use Modules\Housekeeping\Enums\BlockType;
use Modules\Housekeeping\Models\RoomBlock;

/**
 * @extends Factory<RoomBlock>
 */
class RoomBlockFactory extends Factory
{
    use ResolvesRoom;

    protected $model = RoomBlock::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'room_id' => fn (array $attributes): int => $this->roomId((int) $attributes['property_id']),
            'type' => BlockType::OutOfService,
            'from_date' => now()->addDays(40)->toDateString(),
            'to_date' => now()->addDays(42)->toDateString(),
            'reason' => 'Repainting',
            'status' => BlockStatus::Active,
        ];
    }
}
