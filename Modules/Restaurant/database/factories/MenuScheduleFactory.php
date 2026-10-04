<?php

namespace Modules\Restaurant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Restaurant\Database\Factories\Concerns\ResolvesProperty;
use Modules\Restaurant\Models\MenuSchedule;

/**
 * @extends Factory<MenuSchedule>
 */
class MenuScheduleFactory extends Factory
{
    use ResolvesProperty;

    protected $model = MenuSchedule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'outlet_id' => fn (array $attributes): int => $this->outletId((int) $attributes['property_id']),
            'name' => 'Schedule '.fake()->unique()->numberBetween(1, 99999),
            'days_of_week' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
            'start_time' => '07:00',
            'end_time' => '10:30',
            'price_adjustment_percent' => '0.00',
            'is_active' => true,
        ];
    }
}
