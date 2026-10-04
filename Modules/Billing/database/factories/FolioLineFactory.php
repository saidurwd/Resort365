<?php

namespace Modules\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Billing\Database\Factories\Concerns\ResolvesProperty;
use Modules\Billing\Enums\FolioLineType;
use Modules\Billing\Models\Folio;
use Modules\Billing\Models\FolioLine;

/**
 * @extends Factory<FolioLine>
 */
class FolioLineFactory extends Factory
{
    use ResolvesProperty;

    protected $model = FolioLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'folio_id' => fn (array $attributes): int => Folio::factory()->create(['property_id' => $attributes['property_id']])->id,
            'posting_date' => now()->toDateString(),
            'line_type' => FolioLineType::Charge,
            'description' => 'Laundry',
            'quantity' => '1.00',
            'unit_price' => '500.00',
            'amount' => '500.00',
            'tax_amount' => '132.50',
            'total' => '632.50',
        ];
    }
}
