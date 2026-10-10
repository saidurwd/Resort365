<?php

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\BankReconciliation;
use Modules\Accounting\Models\BankStatement;

/**
 * @extends Factory<BankReconciliation>
 */
class BankReconciliationFactory extends Factory
{
    protected $model = BankReconciliation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $statement = BankStatement::factory()->create();

        return [
            'bank_statement_id' => $statement->id, 'bank_account_id' => $statement->bank_account_id, 'statement_date' => $statement->statement_to->toDateString(),
            'statement_balance' => '10000.00', 'book_balance' => '10000.00', 'outstanding_net' => '0.00', 'difference' => '0.00', 'completed_at' => now(),
        ];
    }
}
