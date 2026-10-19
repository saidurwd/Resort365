<?php

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Payroll\Models\SalaryStructure;

/**
 * @extends Factory<SalaryStructure>
 */
class SalaryStructureFactory extends Factory
{
    protected $model = SalaryStructure::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['code' => strtoupper(fake()->unique()->lexify('S????')), 'name' => fake()->words(2, true).' structure', 'is_active' => true];
    }
}
