<?php

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Payroll\Models\TaxSlab;
use Modules\Payroll\Models\TaxTable;

/**
 * @extends Factory<TaxSlab>
 */
class TaxSlabFactory extends Factory
{
    protected $model = TaxSlab::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['tax_table_id' => TaxTable::factory(), 'sort_order' => 1, 'width' => '100000.00', 'rate' => '5.00'];
    }
}
