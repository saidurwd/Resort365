<?php

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Enums\PeriodStatus;
use Modules\Accounting\Models\FiscalPeriod;
use Modules\Accounting\Models\FiscalYear;

/**
 * @extends Factory<FiscalPeriod>
 */
class FiscalPeriodFactory extends Factory
{
    protected $model = FiscalPeriod::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fiscal_year_id' => FiscalYear::factory(),
            'name' => 'January',
            'number' => 1,
            'starts_on' => '2031-01-01',
            'ends_on' => '2031-01-31',
            'status' => PeriodStatus::Open,
        ];
    }
}
