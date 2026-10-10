<?php

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\BankMatch;
use Modules\Accounting\Models\BankStatementLine;
use Modules\Accounting\Models\JournalLine;

/**
 * @extends Factory<BankMatch>
 */
class BankMatchFactory extends Factory
{
    protected $model = BankMatch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['bank_statement_line_id' => BankStatementLine::factory(), 'journal_line_id' => JournalLine::factory(), 'matched_at' => now()];
    }
}
