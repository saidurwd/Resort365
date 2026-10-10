<?php

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\BankStatement;
use Modules\Accounting\Models\BankStatementLine;

/**
 * @extends Factory<BankStatementLine>
 */
class BankStatementLineFactory extends Factory
{
    protected $model = BankStatementLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $statement = BankStatement::factory()->create();

        return [
            'bank_statement_id' => $statement->id, 'bank_account_id' => $statement->bank_account_id, 'line_no' => 1, 'txn_date' => now()->toDateString(),
            'description' => fake()->sentence(3), 'reference' => null, 'withdrawal' => '0.00', 'deposit' => '500.00', 'fingerprint' => sha1((string) fake()->unique()->uuid()),
        ];
    }
}
