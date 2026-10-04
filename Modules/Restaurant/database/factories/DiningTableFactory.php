<?php

namespace Modules\Restaurant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Restaurant\Database\Factories\Concerns\ResolvesProperty;
use Modules\Restaurant\Enums\TableShape;
use Modules\Restaurant\Enums\TableStatus;
use Modules\Restaurant\Models\DiningTable;

/**
 * @extends Factory<DiningTable>
 */
class DiningTableFactory extends Factory
{
    use ResolvesProperty;

    protected $model = DiningTable::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'outlet_id' => fn (array $attributes): int => $this->outletId((int) $attributes['property_id']),
            'dining_area_id' => fn (array $attributes): int => $this->areaId((int) $attributes['outlet_id']),
            'number' => 'T'.fake()->unique()->numberBetween(1, 99999),
            'seats' => 4,
            'shape' => TableShape::Square,
            'pos_x' => fake()->numberBetween(0, 900),
            'pos_y' => fake()->numberBetween(0, 500),
            'status' => TableStatus::Available,
            'is_active' => true,
        ];
    }
}
