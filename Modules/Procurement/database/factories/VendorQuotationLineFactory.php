<?php

namespace Modules\Procurement\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Procurement\Database\Factories\Concerns\ResolvesReferences;
use Modules\Procurement\Models\VendorQuotation;
use Modules\Procurement\Models\VendorQuotationLine;

/**
 * @extends Factory<VendorQuotationLine>
 */
class VendorQuotationLineFactory extends Factory
{
    use ResolvesReferences;

    protected $model = VendorQuotationLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quotation = VendorQuotation::factory()->create();

        return ['property_id' => $quotation->property_id, 'vendor_quotation_id' => $quotation->id, 'item_id' => $this->itemId(), 'quantity' => '10', 'unit_price' => '100'];
    }
}
