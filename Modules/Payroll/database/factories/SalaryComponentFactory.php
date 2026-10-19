<?php

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Payroll\Enums\ComponentFrequency;
use Modules\Payroll\Enums\ComponentKind;
use Modules\Payroll\Models\SalaryComponent;

/**
 * @extends Factory<SalaryComponent>
 */
class SalaryComponentFactory extends Factory
{
    protected $model = SalaryComponent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['code' => strtoupper(fake()->unique()->lexify('C????')), 'name' => fake()->words(2, true), 'kind' => ComponentKind::Earning, 'is_taxable' => true, 'frequency' => ComponentFrequency::Monthly, 'annual_times' => 1, 'is_system' => false, 'sort_order' => 10, 'is_active' => true];
    }
}
