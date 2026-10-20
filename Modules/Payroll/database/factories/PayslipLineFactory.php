<?php

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Payroll\Enums\ComponentKind;
use Modules\Payroll\Enums\LineSource;
use Modules\Payroll\Models\Payslip;
use Modules\Payroll\Models\PayslipLine;

/**
 * @extends Factory<PayslipLine>
 */
class PayslipLineFactory extends Factory
{
    protected $model = PayslipLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['payslip_id' => Payslip::factory(), 'code' => 'BASIC', 'name' => 'Basic salary', 'kind' => ComponentKind::Earning, 'is_taxable' => true, 'amount' => '25000.00', 'source' => LineSource::Structure, 'sort_order' => 10];
    }
}
