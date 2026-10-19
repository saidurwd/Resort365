<?php

namespace Modules\HR\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\HR\Models\Grade;

/**
 * @extends Factory<Grade>
 */
class GradeFactory extends Factory
{
    protected $model = Grade::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['code' => 'G'.fake()->unique()->numerify('####'), 'name' => 'Grade '.fake()->numerify('##'), 'level' => fake()->numberBetween(1, 10), 'is_active' => true];
    }
}
