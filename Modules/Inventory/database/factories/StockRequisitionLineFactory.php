<?php

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\StockRequisition;
use Modules\Inventory\Models\StockRequisitionLine;

/**
 * @extends Factory<StockRequisitionLine>
 */
class StockRequisitionLineFactory extends Factory
{
    protected $model = StockRequisitionLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $requisition = StockRequisition::factory()->create();

        return ['property_id' => $requisition->property_id, 'stock_requisition_id' => $requisition->id, 'item_id' => Item::factory(), 'quantity' => '5', 'issued_quantity' => '0'];
    }
}
