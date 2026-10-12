<?php

namespace Modules\Procurement\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Procurement\Database\Factories\Concerns\ResolvesReferences;
use Modules\Procurement\Models\GoodsReceipt;
use Modules\Procurement\Models\GoodsReceiptLine;
use Modules\Procurement\Models\PurchaseOrderLine;

/**
 * @extends Factory<GoodsReceiptLine>
 */
class GoodsReceiptLineFactory extends Factory
{
    use ResolvesReferences;

    protected $model = GoodsReceiptLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $receipt = GoodsReceipt::factory()->create();
        $line = PurchaseOrderLine::factory()->create(['purchase_order_id' => $receipt->purchase_order_id, 'property_id' => $receipt->property_id]);

        return ['property_id' => $receipt->property_id, 'goods_receipt_id' => $receipt->id, 'purchase_order_line_id' => $line->id, 'item_id' => $line->item_id, 'accepted_quantity' => '10', 'rejected_quantity' => '0', 'unit_cost' => '100', 'value' => '1000', 'returned_quantity' => '0'];
    }
}
