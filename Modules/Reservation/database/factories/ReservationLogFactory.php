<?php

namespace Modules\Reservation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Reservation\Database\Factories\Concerns\ResolvesRoom;
use Modules\Reservation\Enums\ReservationLogAction;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationLog;

/**
 * @extends Factory<ReservationLog>
 */
class ReservationLogFactory extends Factory
{
    use ResolvesRoom;

    protected $model = ReservationLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'reservation_id' => fn (array $attributes): int => Reservation::factory()->create(['property_id' => $attributes['property_id']])->id,
            'action' => ReservationLogAction::Created,
            'description' => 'Booked by phone.',
        ];
    }
}
