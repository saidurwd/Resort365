<?php

namespace Modules\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Billing\Database\Factories\Concerns\ResolvesProperty;
use Modules\Billing\Models\ChargeCode;
use Modules\Billing\Models\ExtraService;

/**
 * @extends Factory<ExtraService>
 */
class ExtraServiceFactory extends Factory
{
    use ResolvesProperty;

    protected $model = ExtraService::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'charge_code_id' => fn (): int => ChargeCode::factory()->create()->id,
            'name' => fake()->randomElement(['Airport pickup', 'Extra bed', 'Laundry (per piece)', 'Spa massage', 'Boat trip']),
            'unit' => 'each',
            'unit_price' => number_format(fake()->numberBetween(2, 40) * 100, 2, '.', ''),
            'is_active' => true,
        ];
    }
}
