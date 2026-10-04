<?php

namespace Modules\Restaurant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Restaurant\Database\Factories\Concerns\ResolvesProperty;
use Modules\Restaurant\Models\DiningArea;

/**
 * @extends Factory<DiningArea>
 */
class DiningAreaFactory extends Factory
{
    use ResolvesProperty;

    protected $model = DiningArea::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'outlet_id' => fn (array $attributes): int => $this->outletId((int) $attributes['property_id']),
            'name' => 'Area '.fake()->unique()->numberBetween(1, 99999),
        ];
    }
}
