<?php

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\StockTransfer;
use Modules\Inventory\Models\StockTransferLine;

/**
 * @extends Factory<StockTransferLine>
 */
class StockTransferLineFactory extends Factory
{
    protected $model = StockTransferLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $transfer = StockTransfer::factory()->create();

        return ['property_id' => $transfer->property_id, 'stock_transfer_id' => $transfer->id, 'item_id' => Item::factory(), 'quantity' => '5', 'unit_cost' => '10'];
    }
}
