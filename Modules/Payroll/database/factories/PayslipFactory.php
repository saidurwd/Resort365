<?php

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Payroll\Database\Factories\Concerns\ResolvesReferences;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\Payslip;

/**
 * @extends Factory<Payslip>
 */
class PayslipFactory extends Factory
{
    use ResolvesReferences;

    protected $model = Payslip::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(), 'payroll_run_id' => PayrollRun::factory(), 'employee_id' => $this->employeeId(), 'employee_no' => 'EMP-'.fake()->numerify('####'), 'employee_name' => fake()->name(),
            'designation' => 'Staff', 'structure_name' => 'Standard monthly staff', 'basic' => '25000.00', 'days_in_month' => 30, 'days_employed' => 30, 'pay_fraction' => '1.0000',
            'gross' => '42500.00', 'deductions' => '2500.00', 'tax' => '416.67', 'net' => '39583.33',
        ];
    }
}
