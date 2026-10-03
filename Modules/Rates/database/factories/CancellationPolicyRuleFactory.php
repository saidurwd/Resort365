<?php

namespace Modules\Rates\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Rates\Database\Factories\Concerns\ResolvesProperty;
use Modules\Rates\Enums\CancellationChargeType;
use Modules\Rates\Models\CancellationPolicy;
use Modules\Rates\Models\CancellationPolicyRule;

/**
 * @extends Factory<CancellationPolicyRule>
 */
class CancellationPolicyRuleFactory extends Factory
{
    use ResolvesProperty;

    protected $model = CancellationPolicyRule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'cancellation_policy_id' => fn (array $attributes): int => CancellationPolicy::factory()->create(['property_id' => $attributes['property_id']])->id,
            'days_before_from' => 7,
            'days_before_to' => 14,
            'charge_type' => CancellationChargeType::PercentOfDeposit,
            'charge_value' => '50.00',
        ];
    }
}
