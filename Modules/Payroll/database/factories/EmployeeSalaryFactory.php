<?php

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Payroll\Database\Factories\Concerns\ResolvesReferences;
use Modules\Payroll\Models\EmployeeSalary;
use Modules\Payroll\Models\SalaryStructure;

/**
 * Employees belong to HR, so the factory takes an existing employee of the tenant or inserts a minimal one (tests only).
 *
 * @extends Factory<EmployeeSalary>
 */
class EmployeeSalaryFactory extends Factory
{
    use ResolvesReferences;

    protected $model = EmployeeSalary::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['employee_id' => 0, 'salary_structure_id' => SalaryStructure::factory(), 'effective_from' => fake()->unique()->dateTimeBetween('-9 years', '-1 year')->format('Y-m-d'), 'basic' => '25000.00', 'tax_category' => 'general', 'tax_area' => 'other'];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (EmployeeSalary $salary): void {
            if ($salary->employee_id === 0) {
                $salary->employee_id = $this->employeeId();
            }
        });
    }
}
