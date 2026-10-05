<?php

namespace Modules\Restaurant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Restaurant\Database\Factories\Concerns\ResolvesProperty;
use Modules\Restaurant\Enums\OrderStatus;
use Modules\Restaurant\Enums\OrderType;
use Modules\Restaurant\Models\PosOrder;

/**
 * @extends Factory<PosOrder>
 */
class PosOrderFactory extends Factory
{
    use ResolvesProperty;

    protected $model = PosOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'outlet_id' => fn (array $attributes): int => $this->outletId((int) $attributes['property_id']),
            'order_no' => 'O-'.fake()->unique()->numberBetween(1000, 999999),
            'business_date' => now()->toDateString(),
            'order_type' => OrderType::Takeaway,
            'covers' => 1,
            'waiter_id' => fn (array $attributes): int => $this->userId((int) $attributes['property_id']),
            'status' => OrderStatus::Cancelled,
            'subtotal' => '0.00',
            'opened_at' => now(),
        ];
    }
}
