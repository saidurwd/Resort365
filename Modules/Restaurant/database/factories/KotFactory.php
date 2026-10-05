<?php

namespace Modules\Restaurant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Restaurant\Database\Factories\Concerns\ResolvesProperty;
use Modules\Restaurant\Enums\KotStatus;
use Modules\Restaurant\Enums\KotType;
use Modules\Restaurant\Models\Kot;
use Modules\Restaurant\Models\PosOrder;

/**
 * @extends Factory<Kot>
 */
class KotFactory extends Factory
{
    use ResolvesProperty;

    protected $model = Kot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'outlet_id' => fn (array $attributes): int => $this->outletId((int) $attributes['property_id']),
            'pos_order_id' => fn (array $attributes): int => PosOrder::factory()->create(['property_id' => $attributes['property_id'], 'outlet_id' => $attributes['outlet_id']])->id,
            'kot_no' => fake()->unique()->numberBetween(1, 999999),
            'business_date' => now()->toDateString(),
            'type' => KotType::New,
            'status' => KotStatus::New,
            'fired_at' => now(),
        ];
    }
}
