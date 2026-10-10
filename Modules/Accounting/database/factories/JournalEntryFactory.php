<?php

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Enums\JournalStatus;
use Modules\Accounting\Models\JournalEntry;

/**
 * @extends Factory<JournalEntry>
 */
class JournalEntryFactory extends Factory
{
    protected $model = JournalEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entry_date' => now()->toDateString(),
            'status' => JournalStatus::Draft,
            'description' => fake()->randomElement(['Monthly accrual', 'Opening balance', 'Correction', 'Owner contribution']),
            'total' => '0.00',
        ];
    }
}
