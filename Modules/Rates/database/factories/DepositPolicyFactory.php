<?php

namespace Modules\Rates\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Rates\Database\Factories\Concerns\ResolvesProperty;
use Modules\Rates\Enums\BalanceDueRule;
use Modules\Rates\Enums\DepositType;
use Modules\Rates\Models\DepositPolicy;

/**
 * @extends Factory<DepositPolicy>
 */
class DepositPolicyFactory extends Factory
{
    use ResolvesProperty;

    protected $model = DepositPolicy::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'name' => 'Standard '.fake()->unique()->numberBetween(1, 99999),
            'type' => DepositType::Percentage,
            'min_percent' => null,
            'default_percent' => '30.00',
            'max_percent' => null,
            'fixed_amount' => null,
            'due_within_minutes' => 30,
            'auto_cancel_unpaid' => true,
            'balance_due_rule' => BalanceDueRule::AtCheckIn,
            'balance_due_days' => null,
            'full_payment_within_hours' => null,
            'is_default' => false,
        ];
    }
}
