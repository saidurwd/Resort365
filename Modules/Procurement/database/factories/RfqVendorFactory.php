<?php

namespace Modules\Procurement\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Procurement\Models\Rfq;
use Modules\Procurement\Models\RfqVendor;
use Modules\Procurement\Models\Vendor;

/**
 * @extends Factory<RfqVendor>
 */
class RfqVendorFactory extends Factory
{
    protected $model = RfqVendor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $rfq = Rfq::factory()->create();

        return ['property_id' => $rfq->property_id, 'rfq_id' => $rfq->id, 'vendor_id' => Vendor::factory()];
    }
}
