<?php

namespace Modules\Reservation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Reservation\Database\Factories\Concerns\ResolvesRoom;
use Modules\Reservation\Models\ReservationItem;
use Modules\Reservation\Models\ReservationItemNight;

/**
 * @extends Factory<ReservationItemNight>
 */
class ReservationItemNightFactory extends Factory
{
    use ResolvesRoom;

    protected $model = ReservationItemNight::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'reservation_item_id' => fn (array $attributes): int => ReservationItem::factory()->create(['property_id' => $attributes['property_id']])->id,
            'stay_date' => fake()->unique()->dateTimeBetween('+1 week', '+3 years')->format('Y-m-d'),
            'base_rate' => '6000.00',
            'net_amount' => '6000.00',
            'tax_amount' => '1590.00',
            'total_amount' => '7590.00',
            'rate_source' => 'base',
        ];
    }
}
