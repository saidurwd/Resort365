<?php

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Payroll\Models\TaxTable;

/**
 * @extends Factory<TaxTable>
 */
class TaxTableFactory extends Factory
{
    protected $model = TaxTable::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Income tax '.fake()->numerify('20##-##'), 'tax_year' => fake()->numerify('20##-##'), 'effective_from' => now()->startOfYear()->toDateString(),
            'thresholds' => [['code' => 'general', 'label' => 'General', 'amount' => '350000.00']], 'minimum_tax' => [['code' => 'other', 'label' => 'Elsewhere', 'amount' => '3000.00']],
            'exemption_fraction' => '1/3', 'exemption_cap' => '450000.00', 'is_active' => true,
        ];
    }
}
