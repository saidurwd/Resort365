<?php

namespace Modules\Reservation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Reservation\Database\Factories\Concerns\ResolvesRoom;
use Modules\Reservation\Enums\ItemType;
use Modules\Reservation\Models\Quote;
use Modules\Reservation\Models\QuoteItem;

/**
 * @extends Factory<QuoteItem>
 */
class QuoteItemFactory extends Factory
{
    use ResolvesRoom;

    protected $model = QuoteItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'quote_id' => fn (array $attributes): int => Quote::factory()->create(['property_id' => $attributes['property_id']])->id,
            'item_type' => ItemType::Room,
            'room_id' => fn (array $attributes): int => $this->roomId((int) $attributes['property_id']),
            'cottage_id' => fn (array $attributes): int => $this->cottageIdOf((int) $attributes['room_id']),
            'room_type_id' => fn (array $attributes): ?int => $this->roomTypeIdOf((int) $attributes['room_id']),
            'rate_plan_id' => fn (array $attributes): int => $this->ratePlanId((int) $attributes['property_id']),
            'unit_key' => fn (array $attributes): string => 'room_type:'.$attributes['room_type_id'],
            'label' => 'Room 101',
            'adults' => 2,
            'subtotal' => '12000.00',
            'tax' => '3180.00',
            'total' => '15180.00',
        ];
    }
}
