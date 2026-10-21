<?php

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\Budget;
use Modules\Accounting\Models\FiscalYear;

/**
 * @extends Factory<Budget>
 */
class BudgetFactory extends Factory
{
    protected $model = Budget::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['fiscal_year_id' => FiscalYear::factory(), 'property_id' => null, 'name' => 'Budget '.fake()->unique()->numberBetween(2030, 2999)];
    }
}
