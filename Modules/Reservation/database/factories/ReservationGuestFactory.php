<?php

namespace Modules\Reservation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Reservation\Database\Factories\Concerns\ResolvesRoom;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationGuest;

/**
 * @extends Factory<ReservationGuest>
 */
class ReservationGuestFactory extends Factory
{
    use ResolvesRoom;

    protected $model = ReservationGuest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'reservation_id' => fn (array $attributes): int => Reservation::factory()->create(['property_id' => $attributes['property_id']])->id,
            'guest_id' => fn (array $attributes): int => $this->guestId((int) $attributes['property_id']),
            'is_primary' => true,
        ];
    }
}
