<?php

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Payroll\Enums\CalcType;
use Modules\Payroll\Models\SalaryComponent;
use Modules\Payroll\Models\SalaryStructure;
use Modules\Payroll\Models\SalaryStructureComponent;

/**
 * @extends Factory<SalaryStructureComponent>
 */
class SalaryStructureComponentFactory extends Factory
{
    protected $model = SalaryStructureComponent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['salary_structure_id' => SalaryStructure::factory(), 'salary_component_id' => SalaryComponent::factory(), 'calc_type' => CalcType::Fixed, 'amount' => '1000.00', 'sort_order' => 10];
    }
}
