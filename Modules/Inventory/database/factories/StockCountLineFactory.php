<?php

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\StockCount;
use Modules\Inventory\Models\StockCountLine;

/**
 * @extends Factory<StockCountLine>
 */
class StockCountLineFactory extends Factory
{
    protected $model = StockCountLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $count = StockCount::factory()->create();

        return ['property_id' => $count->property_id, 'stock_count_id' => $count->id, 'item_id' => Item::factory(), 'system_quantity' => '10', 'unit_cost' => '5', 'snapshot_movement_id' => 0];
    }
}
