<?php

namespace Modules\Procurement\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Procurement\Models\GoodsReceipt;
use Modules\Procurement\Models\PurchaseOrder;

/**
 * @extends Factory<GoodsReceipt>
 */
class GoodsReceiptFactory extends Factory
{
    protected $model = GoodsReceipt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $order = PurchaseOrder::factory()->create();

        return ['property_id' => $order->property_id, 'receipt_no' => 'GRN-'.fake()->unique()->numerify('####-#####'), 'purchase_order_id' => $order->id, 'vendor_id' => $order->vendor_id, 'store_id' => 1, 'received_on' => now()->toDateString(), 'received_by' => 1, 'total_value' => '1000'];
    }
}
