<?php

namespace Modules\Property\Database\Factories;

use App\Support\Tenancy\PropertyContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Property\Models\Cottage;
use Modules\Property\Models\Property;
use Modules\Property\Models\Room;
use Modules\Property\Models\RoomType;

/**
 * Rooms (in a cottage and of a room type of the same property) of the current property, or of a new property.
 *
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    protected $model = Room::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => fn (): mixed => app(PropertyContext::class)->currentId() ?? Property::factory(),
            'cottage_id' => fn (array $attributes): int => Cottage::factory()->create(['property_id' => $attributes['property_id']])->id,
            'room_type_id' => fn (array $attributes): int => RoomType::factory()->create(['property_id' => $attributes['property_id']])->id,
            'number' => (string) fake()->unique()->numberBetween(100, 9999),
            'floor' => (string) fake()->numberBetween(0, 2),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
