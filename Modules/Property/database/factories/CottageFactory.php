<?php

namespace Modules\Property\Database\Factories;

use App\Support\Tenancy\PropertyContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Property\Enums\BookingMode;
use Modules\Property\Enums\CottageStatus;
use Modules\Property\Models\Cottage;
use Modules\Property\Models\CottageType;
use Modules\Property\Models\Property;

/**
 * Cottages (with a cottage type of the same property) of the current property, or of a new property.
 *
 * @extends Factory<Cottage>
 */
class CottageFactory extends Factory
{
    protected $model = Cottage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => fn (): mixed => app(PropertyContext::class)->currentId() ?? Property::factory(),
            'cottage_type_id' => fn (array $attributes): int => CottageType::factory()->create(['property_id' => $attributes['property_id']])->id,
            'code' => strtoupper(fake()->unique()->bothify('C-##??')),
            'name' => fake()->randomElement(['Sea Breeze', 'Coral', 'Palm', 'Sunset', 'Lagoon', 'Tea Garden', 'Hilltop']).' '.fake()->numberBetween(1, 99),
            'zone' => fake()->randomElement(['Beachfront', 'Garden', 'Hillside']),
            'booking_mode' => BookingMode::Both,
            'max_occupancy_override' => null,
            'status' => CottageStatus::Active,
            'sort_order' => 0,
            'description' => fake()->sentence(10),
        ];
    }
}
