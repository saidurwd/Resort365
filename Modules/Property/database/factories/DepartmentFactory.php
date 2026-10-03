<?php

namespace Modules\Property\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Property\Models\Department;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('D??##')),
            'name' => fake()->randomElement(['Front Office', 'Housekeeping', 'Food & Beverage', 'Maintenance', 'Administration']),
            'description' => fake()->sentence(8),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
