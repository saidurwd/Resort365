<?php

namespace Modules\HR\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\HR\Models\Shift;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['code' => 'S'.fake()->unique()->numerify('###'), 'name' => fake()->unique()->word().' shift', 'starts_at' => '09:00:00', 'ends_at' => '17:00:00', 'break_minutes' => 30, 'grace_minutes' => 10, 'overtime_after_minutes' => 15, 'color' => 'primary', 'is_active' => true];
    }
}
