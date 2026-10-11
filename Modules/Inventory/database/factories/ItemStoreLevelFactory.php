<?php

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\ItemStoreLevel;
use Modules\Inventory\Models\Store;

/**
 * @extends Factory<ItemStoreLevel>
 */
class ItemStoreLevelFactory extends Factory
{
    protected $model = ItemStoreLevel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['item_id' => Item::factory(), 'store_id' => Store::factory(), 'par_level' => '100', 'reorder_level' => '20'];
    }
}
