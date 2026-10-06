<?php

namespace Modules\Restaurant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Restaurant\Database\Factories\Concerns\ResolvesProperty;
use Modules\Restaurant\Enums\TableReservationStatus;
use Modules\Restaurant\Models\TableReservation;

/**
 * @extends Factory<TableReservation>
 */
class TableReservationFactory extends Factory
{
    use ResolvesProperty;

    protected $model = TableReservation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'outlet_id' => fn (array $attributes): int => $this->outletId((int) $attributes['property_id']),
            'customer_name' => fake()->name(),
            'phone' => fake()->numerify('017########'),
            'reserved_for' => now()->addDay()->setTime(19, 30),
            'duration_minutes' => 90,
            'party_size' => 2,
            'status' => TableReservationStatus::Booked,
        ];
    }
}
