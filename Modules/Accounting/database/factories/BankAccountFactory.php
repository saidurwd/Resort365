<?php

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Enums\AccountType;
use Modules\Accounting\Enums\BankAccountKind;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\BankAccount;

/**
 * @extends Factory<BankAccount>
 */
class BankAccountFactory extends Factory
{
    protected $model = BankAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory()->state(['type' => AccountType::Asset]), 'name' => fake()->company().' current', 'kind' => BankAccountKind::Bank,
            'bank_name' => fake()->company(), 'account_number' => fake()->numerify('##########'), 'is_active' => true,
        ];
    }
}
