<?php

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Enums\AccountType;
use Modules\Accounting\Models\Account;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => (string) fake()->unique()->numberBetween(10000, 99999),
            'name' => fake()->randomElement(['Cash on hand', 'Bank account', 'Room revenue', 'Food revenue', 'Utilities', 'Repairs and maintenance']).' '.fake()->unique()->numberBetween(1, 99999),
            'type' => AccountType::Asset,
            'is_group' => false,
            'is_active' => true,
        ];
    }

    public function ofType(AccountType $type): static
    {
        return $this->state(['type' => $type]);
    }

    public function group(): static
    {
        return $this->state(['is_group' => true]);
    }
}
