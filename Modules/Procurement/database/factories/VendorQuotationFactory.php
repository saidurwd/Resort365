<?php

namespace Modules\Procurement\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Procurement\Enums\QuotationStatus;
use Modules\Procurement\Models\Rfq;
use Modules\Procurement\Models\Vendor;
use Modules\Procurement\Models\VendorQuotation;

/**
 * @extends Factory<VendorQuotation>
 */
class VendorQuotationFactory extends Factory
{
    protected $model = VendorQuotation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $rfq = Rfq::factory()->create();

        return ['property_id' => $rfq->property_id, 'rfq_id' => $rfq->id, 'vendor_id' => Vendor::factory(), 'quoted_on' => now()->toDateString(), 'total' => '1000', 'status' => QuotationStatus::Received];
    }
}
