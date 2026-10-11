<?php

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Database\Factories\Concerns\ResolvesProperty;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\StockBalance;
use Modules\Inventory\Models\Store;

/**
 * @extends Factory<StockBalance>
 */
class StockBalanceFactory extends Factory
{
    use ResolvesProperty;

    protected $model = StockBalance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $store = Store::factory()->create();

        return ['property_id' => $store->property_id, 'store_id' => $store->id, 'item_id' => Item::factory(), 'quantity' => '10', 'avg_cost' => '5'];
    }
}
