<?php

namespace Modules\Restaurant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Restaurant\Database\Factories\Concerns\ResolvesProperty;
use Modules\Restaurant\Models\PosBill;
use Modules\Restaurant\Models\PosBillLine;
use Modules\Restaurant\Models\PosOrderLine;

/**
 * @extends Factory<PosBillLine>
 */
class PosBillLineFactory extends Factory
{
    use ResolvesProperty;

    protected $model = PosBillLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'pos_bill_id' => fn (array $attributes): int => PosBill::factory()->create(['property_id' => $attributes['property_id']])->id,
            'pos_order_line_id' => fn (array $attributes): int => PosOrderLine::factory()->create([
                'property_id' => $attributes['property_id'], 'pos_order_id' => PosBill::query()->whereKey($attributes['pos_bill_id'])->value('pos_order_id'),
            ])->id,
            'name_snapshot' => 'Chicken curry',
            'quantity' => '1.000',
            'amount' => '650.00',
            'discount' => '0.00',
            'tax' => '107.25',
            'gross' => '757.25',
        ];
    }
}
