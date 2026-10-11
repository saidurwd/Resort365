<?php

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\ItemUnitConversion;
use Modules\Inventory\Models\Unit;

/**
 * @extends Factory<ItemUnitConversion>
 */
class ItemUnitConversionFactory extends Factory
{
    protected $model = ItemUnitConversion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['item_id' => Item::factory(), 'unit_id' => Unit::factory(), 'factor' => '24'];
    }
}
