<?php

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalLine;

/**
 * @extends Factory<JournalLine>
 */
class JournalLineFactory extends Factory
{
    protected $model = JournalLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'journal_entry_id' => JournalEntry::factory(),
            'line_no' => 1,
            'account_id' => Account::factory(),
            'debit' => '100.00',
            'credit' => '0.00',
        ];
    }
}
