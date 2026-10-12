<?php

namespace Modules\Procurement\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Procurement\Database\Factories\Concerns\ResolvesReferences;
use Modules\Procurement\Enums\PurchaseOrderStatus;
use Modules\Procurement\Models\PurchaseOrder;
use Modules\Procurement\Models\Vendor;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    use ResolvesReferences;

    protected $model = PurchaseOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['property_id' => $this->propertyId(), 'order_no' => 'PO-'.fake()->unique()->numerify('####-#####'), 'vendor_id' => Vendor::factory(), 'store_id' => 1, 'status' => PurchaseOrderStatus::Draft, 'ordered_on' => now()->toDateString(), 'currency' => 'BDT', 'subtotal' => '1000', 'tax_total' => '0', 'total' => '1000', 'created_by' => 1];
    }
}
