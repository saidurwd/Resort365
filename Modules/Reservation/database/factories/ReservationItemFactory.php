<?php

namespace Modules\Reservation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Reservation\Database\Factories\Concerns\ResolvesRoom;
use Modules\Reservation\Enums\ItemType;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationItem;

/**
 * @extends Factory<ReservationItem>
 */
class ReservationItemFactory extends Factory
{
    use ResolvesRoom;

    protected $model = ReservationItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'reservation_id' => fn (array $attributes): int => Reservation::factory()->create(['property_id' => $attributes['property_id']])->id,
            'item_type' => ItemType::Room,
            'room_id' => fn (array $attributes): int => $this->roomId((int) $attributes['property_id']),
            'cottage_id' => fn (array $attributes): int => $this->cottageIdOf((int) $attributes['room_id']),
            'rate_plan_id' => fn (array $attributes): int => $this->ratePlanId((int) $attributes['property_id']),
            'check_in' => '2026-12-10',
            'check_out' => '2026-12-12',
            'adults' => 2,
            'children' => 0,
            'status' => ReservationStatus::Tentative,
            'subtotal' => '12000.00',
            'total' => '15180.00',
        ];
    }
}
