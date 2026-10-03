<?php

namespace Modules\Rates\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Rates\Database\Factories\Concerns\ResolvesProperty;
use Modules\Rates\Enums\CancellationChargeType;
use Modules\Rates\Models\CancellationPolicy;

/**
 * @extends Factory<CancellationPolicy>
 */
class CancellationPolicyFactory extends Factory
{
    use ResolvesProperty;

    protected $model = CancellationPolicy::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'name' => 'Flexible '.fake()->unique()->numberBetween(1, 99999),
            'no_show_charge_type' => CancellationChargeType::PercentOfDeposit,
            'no_show_charge_value' => '100.00',
            'is_default' => false,
        ];
    }
}
