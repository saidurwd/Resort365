<?php

namespace Modules\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Billing\Database\Factories\Concerns\ResolvesProperty;
use Modules\Billing\Models\CreditNote;
use Modules\Billing\Models\Invoice;

/**
 * @extends Factory<CreditNote>
 */
class CreditNoteFactory extends Factory
{
    use ResolvesProperty;

    protected $model = CreditNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'invoice_id' => fn (array $attributes): int => Invoice::factory()->create(['property_id' => $attributes['property_id']])->id,
            'credit_note_no' => 'CN-TEST-'.fake()->unique()->numberBetween(10000, 999999),
            'issue_date' => now()->toDateString(),
            'amount' => '500.00',
            'reason' => 'Laundry charged twice',
            'refund_due' => '500.00',
        ];
    }
}
