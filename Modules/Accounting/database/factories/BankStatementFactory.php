<?php

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\BankAccount;
use Modules\Accounting\Models\BankStatement;

/**
 * @extends Factory<BankStatement>
 */
class BankStatementFactory extends Factory
{
    protected $model = BankStatement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['bank_account_id' => BankAccount::factory(), 'file_name' => 'statement.csv', 'statement_from' => now()->startOfMonth()->toDateString(), 'statement_to' => now()->toDateString(), 'closing_balance' => '10000.00', 'line_count' => 0];
    }
}
