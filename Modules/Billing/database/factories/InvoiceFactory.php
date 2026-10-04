<?php

namespace Modules\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Billing\Database\Factories\Concerns\ResolvesProperty;
use Modules\Billing\Enums\BillTo;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Models\Folio;
use Modules\Billing\Models\Invoice;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    use ResolvesProperty;

    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'folio_id' => fn (array $attributes): int => Folio::factory()->create(['property_id' => $attributes['property_id']])->id,
            'invoice_no' => 'INV-TEST-'.fake()->unique()->numberBetween(10000, 999999),
            'issue_date' => now()->toDateString(),
            'bill_to_type' => BillTo::Guest,
            'bill_to_name' => fake()->name(),
            'currency_code' => 'BDT',
            'subtotal' => '12000.00',
            'tax_total' => '3180.00',
            'total' => '15180.00',
            'paid' => '15180.00',
            'tax_breakdown' => ['Service charge' => '1200.00', 'VAT' => '1980.00'],
            'status' => InvoiceStatus::Issued,
        ];
    }
}
