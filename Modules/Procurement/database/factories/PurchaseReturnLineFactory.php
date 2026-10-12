<?php

namespace Modules\Procurement\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Procurement\Models\GoodsReceiptLine;
use Modules\Procurement\Models\PurchaseReturn;
use Modules\Procurement\Models\PurchaseReturnLine;

/**
 * @extends Factory<PurchaseReturnLine>
 */
class PurchaseReturnLineFactory extends Factory
{
    protected $model = PurchaseReturnLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $return = PurchaseReturn::factory()->create();
        $line = GoodsReceiptLine::factory()->create(['goods_receipt_id' => $return->goods_receipt_id, 'property_id' => $return->property_id]);

        return ['property_id' => $return->property_id, 'purchase_return_id' => $return->id, 'goods_receipt_line_id' => $line->id, 'item_id' => $line->item_id, 'quantity' => '1', 'unit_cost' => '100', 'value' => '100', 'grni_value' => '100', 'billed_value' => '0', 'tax_value' => '0', 'stock_cost' => '100'];
    }
}
