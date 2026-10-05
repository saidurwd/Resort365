<?php

namespace Modules\Restaurant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Restaurant\Database\Factories\Concerns\ResolvesProperty;
use Modules\Restaurant\Enums\KotStatus;
use Modules\Restaurant\Models\Kot;
use Modules\Restaurant\Models\KotLine;
use Modules\Restaurant\Models\PosOrderLine;

/**
 * @extends Factory<KotLine>
 */
class KotLineFactory extends Factory
{
    use ResolvesProperty;

    protected $model = KotLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'kot_id' => fn (array $attributes): int => Kot::factory()->create(['property_id' => $attributes['property_id']])->id,
            'pos_order_line_id' => fn (array $attributes): int => PosOrderLine::factory()->create(['property_id' => $attributes['property_id']])->id,
            'quantity' => 1,
            'status' => KotStatus::New,
        ];
    }
}
