<?php

namespace Modules\HR\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\HR\Database\Factories\Concerns\ResolvesReferences;
use Modules\HR\Models\Holiday;

/**
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    use ResolvesReferences;

    protected $model = Holiday::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['property_id' => $this->propertyId(), 'name' => fake()->words(2, true), 'holiday_date' => fake()->dateTimeBetween('now', '+1 year')->format('Y-m-d'), 'is_paid' => true, 'repeats_yearly' => false];
    }
}
