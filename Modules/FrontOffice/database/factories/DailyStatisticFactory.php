<?php

namespace Modules\FrontOffice\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\FrontOffice\Database\Factories\Concerns\ResolvesProperty;
use Modules\FrontOffice\Models\DailyStatistic;

/**
 * A past day at a 15-room resort: 9 of 15 rooms sold at 6,000 (dates are unique per property).
 *
 * @extends Factory<DailyStatistic>
 */
class DailyStatisticFactory extends Factory
{
    use ResolvesProperty;

    protected $model = DailyStatistic::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'business_date' => now()->subDays(fake()->unique()->numberBetween(30, 3000))->toDateString(),
            'rooms_total' => 15,
            'rooms_out_of_order' => 0,
            'rooms_blocked' => 0,
            'rooms_available' => 15,
            'rooms_occupied' => 9,
            'occupancy_percent' => '60.00',
            'adr' => '6000.00',
            'revpar' => '3600.00',
            'room_revenue' => '54000.00',
            'package_meal_revenue' => '0.00',
            'room_tax' => '14310.00',
            'charges_total' => '68310.00',
            'received_total' => '40000.00',
            'refunded_total' => '0.00',
            'adults' => 18,
            'children' => 2,
            'arrivals' => 3,
            'departures' => 2,
            'no_shows' => 0,
            'takings' => ['charges' => [], 'payments' => []],
        ];
    }
}
