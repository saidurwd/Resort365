<?php

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\ItemCategory;
use Modules\Inventory\Models\Unit;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    protected $model = Item::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_category_id' => ItemCategory::factory(), 'sku' => 'SKU-'.fake()->unique()->numerify('######'), 'name' => fake()->unique()->words(3, true), 'base_unit_id' => Unit::factory(),
            'is_stockable' => true, 'track_batches' => false, 'track_expiry' => false, 'reorder_level' => '0', 'reorder_qty' => '0', 'is_active' => true,
        ];
    }
}
