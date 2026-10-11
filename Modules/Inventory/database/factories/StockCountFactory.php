<?php

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Enums\CountStatus;
use Modules\Inventory\Models\StockCount;
use Modules\Inventory\Models\Store;

/**
 * @extends Factory<StockCount>
 */
class StockCountFactory extends Factory
{
    protected $model = StockCount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $store = Store::factory()->create();

        return ['property_id' => $store->property_id, 'count_no' => 'SC-'.fake()->unique()->numerify('####-#####'), 'store_id' => $store->id, 'status' => CountStatus::Counting, 'blind' => false, 'started_at' => now()];
    }
}
