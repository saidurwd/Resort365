<?php

namespace Modules\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Billing\Database\Factories\Concerns\ResolvesProperty;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;

/**
 * @extends Factory<InvoiceLine>
 */
class InvoiceLineFactory extends Factory
{
    use ResolvesProperty;

    protected $model = InvoiceLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'invoice_id' => fn (array $attributes): int => Invoice::factory()->create(['property_id' => $attributes['property_id']])->id,
            'service_date' => now()->toDateString(),
            'charge_code' => 'ROOM',
            'description' => 'Room 401',
            'amount' => '6000.00',
            'tax_amount' => '1590.00',
            'total' => '7590.00',
        ];
    }
}
