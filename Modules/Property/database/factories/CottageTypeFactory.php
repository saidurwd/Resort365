<?php

namespace Modules\Property\Database\Factories;

use App\Support\Tenancy\PropertyContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Property\Models\CottageType;
use Modules\Property\Models\Property;

/**
 * Cottage types of the current property, or of a new property when there is none.
 *
 * @extends Factory<CottageType>
 */
class CottageTypeFactory extends Factory
{
    protected $model = CottageType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        [$name, $bedrooms, $occupancy] = fake()->randomElement([
            ['Honeymoon Cottage', 1, 2], ['Garden Cottage', 2, 4], ['Family Villa', 3, 8], ['Hill View Bungalow', 2, 5],
        ]);

        return [
            'property_id' => fn (): mixed => app(PropertyContext::class)->currentId() ?? Property::factory(),
            'code' => strtoupper(fake()->unique()->bothify('CT??##')),
            'name' => $name,
            'description' => fake()->sentence(12),
            'max_occupancy' => $occupancy,
            'bedrooms' => $bedrooms,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
