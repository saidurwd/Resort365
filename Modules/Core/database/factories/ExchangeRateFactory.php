<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Currency;
use Modules\Core\Models\ExchangeRate;

/**
 * @extends Factory<ExchangeRate>
 */
class ExchangeRateFactory extends Factory
{
    protected $model = ExchangeRate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        foreach (['USD' => 'US Dollar', 'BDT' => 'Bangladeshi Taka'] as $code => $name) {
            Currency::query()->firstOrCreate(['code' => $code], ['name' => $name, 'symbol' => $code, 'decimals' => 2]);
        }

        return [
            'base_currency' => 'USD',
            'quote_currency' => 'BDT',
            'rate' => '122.50000000',
            'effective_date' => fake()->unique()->dateTimeBetween('-2 years')->format('Y-m-d'),
            'source' => 'manual',
        ];
    }
}
