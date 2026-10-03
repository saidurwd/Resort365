<?php

namespace Modules\Property\Database\Factories;

use App\Support\Tenancy\PropertyContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Property\Models\Property;
use Modules\Property\Models\RoomType;

/**
 * Room types of the current property, or of a new property when there is none.
 *
 * @extends Factory<RoomType>
 */
class RoomTypeFactory extends Factory
{
    protected $model = RoomType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        [$name, $beds, $adults, $children, $max] = fake()->randomElement([
            ['Deluxe King', '1 king', 2, 1, 3], ['Twin', '2 single', 2, 1, 3], ['Family Suite', '1 king + 2 single', 4, 2, 5],
        ]);

        return [
            'property_id' => fn (): mixed => app(PropertyContext::class)->currentId() ?? Property::factory(),
            'code' => strtoupper(fake()->unique()->bothify('RT??##')),
            'name' => $name,
            'description' => fake()->sentence(10),
            'base_occupancy' => 2,
            'max_adults' => $adults,
            'max_children' => $children,
            'max_occupancy' => $max,
            'bed_configuration' => $beds,
            'size_sqm' => (string) fake()->numberBetween(24, 60),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
