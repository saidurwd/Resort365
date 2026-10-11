<?php

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\Store;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $store = Store::factory()->create();

        return [
            'property_id' => $store->property_id, 'store_id' => $store->id, 'item_id' => Item::factory(), 'movement_type' => MovementType::Receipt, 'movement_date' => now()->toDateString(),
            'quantity' => '10', 'unit_cost' => '5', 'total_cost' => '50', 'quantity_after' => '10', 'avg_cost_after' => '5',
        ];
    }
}
