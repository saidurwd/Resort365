<?php

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\AccountMapping;

/**
 * @extends Factory<AccountMapping>
 */
class AccountMappingFactory extends Factory
{
    protected $model = AccountMapping::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['mapping_key' => 'charge:'.strtoupper(fake()->unique()->lexify('????')), 'account_id' => Account::factory()];
    }
}
