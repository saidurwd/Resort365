<?php

namespace Modules\Restaurant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Restaurant\Database\Factories\Concerns\ResolvesProperty;
use Modules\Restaurant\Enums\BillStatus;
use Modules\Restaurant\Models\PosBill;
use Modules\Restaurant\Models\PosOrder;

/**
 * @extends Factory<PosBill>
 */
class PosBillFactory extends Factory
{
    use ResolvesProperty;

    protected $model = PosBill::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sequence = fake()->unique()->numberBetween(1, 999999);

        return [
            'property_id' => $this->propertyId(...),
            'outlet_id' => fn (array $attributes): int => $this->outletId((int) $attributes['property_id']),
            'pos_order_id' => fn (array $attributes): int => PosOrder::factory()->create(['property_id' => $attributes['property_id'], 'outlet_id' => $attributes['outlet_id']])->id,
            'sequence' => $sequence,
            'bill_no' => 'R-B'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
            'business_date' => now()->toDateString(),
            'subtotal' => '1000.00',
            'discount_total' => '0.00',
            'service_charge' => '100.00',
            'tax_total' => '165.00',
            'grand_total' => '1265.00',
            'status' => BillStatus::Printed,
            'print_count' => 1,
            'printed_at' => now(),
        ];
    }
}
