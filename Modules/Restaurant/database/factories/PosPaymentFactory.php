<?php

namespace Modules\Restaurant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Restaurant\Database\Factories\Concerns\ResolvesProperty;
use Modules\Restaurant\Enums\PaymentMethod;
use Modules\Restaurant\Models\PosBill;
use Modules\Restaurant\Models\PosPayment;

/**
 * @extends Factory<PosPayment>
 */
class PosPaymentFactory extends Factory
{
    use ResolvesProperty;

    protected $model = PosPayment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'outlet_id' => fn (array $attributes): int => $this->outletId((int) $attributes['property_id']),
            'pos_bill_id' => fn (array $attributes): int => PosBill::factory()->create(['property_id' => $attributes['property_id'], 'outlet_id' => $attributes['outlet_id']])->id,
            'business_date' => now()->toDateString(),
            'method' => PaymentMethod::Card,
            'amount' => '1265.00',
            'reference' => (string) fake()->numberBetween(100000, 999999),
        ];
    }
}
