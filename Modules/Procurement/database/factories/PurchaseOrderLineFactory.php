<?php

namespace Modules\Procurement\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Procurement\Database\Factories\Concerns\ResolvesReferences;
use Modules\Procurement\Models\PurchaseOrder;
use Modules\Procurement\Models\PurchaseOrderLine;

/**
 * @extends Factory<PurchaseOrderLine>
 */
class PurchaseOrderLineFactory extends Factory
{
    use ResolvesReferences;

    protected $model = PurchaseOrderLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $order = PurchaseOrder::factory()->create();

        return ['property_id' => $order->property_id, 'purchase_order_id' => $order->id, 'item_id' => $this->itemId(), 'quantity' => '10', 'unit_price' => '100', 'net_amount' => '1000', 'tax_amount' => '0', 'line_total' => '1000'];
    }
}
