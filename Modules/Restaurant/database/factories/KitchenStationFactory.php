<?php

namespace Modules\Restaurant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Restaurant\Database\Factories\Concerns\ResolvesProperty;
use Modules\Restaurant\Enums\StationOutput;
use Modules\Restaurant\Models\KitchenStation;

/**
 * @extends Factory<KitchenStation>
 */
class KitchenStationFactory extends Factory
{
    use ResolvesProperty;

    protected $model = KitchenStation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'outlet_id' => fn (array $attributes): int => $this->outletId((int) $attributes['property_id']),
            'name' => 'Station '.fake()->unique()->numberBetween(1, 99999),
            'output' => StationOutput::Display,
        ];
    }
}
