<?php

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\FiscalYear;

/**
 * @extends Factory<FiscalYear>
 */
class FiscalYearFactory extends Factory
{
    protected $model = FiscalYear::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = fake()->unique()->numberBetween(2030, 2999);

        return ['name' => 'FY '.$year, 'starts_on' => $year.'-01-01', 'ends_on' => $year.'-12-31'];
    }
}
