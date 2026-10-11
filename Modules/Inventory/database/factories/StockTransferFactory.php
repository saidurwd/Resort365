<?php

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Enums\TransferStatus;
use Modules\Inventory\Models\StockTransfer;
use Modules\Inventory\Models\Store;

/**
 * @extends Factory<StockTransfer>
 */
class StockTransferFactory extends Factory
{
    protected $model = StockTransfer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $from = Store::factory()->create();

        return [
            'property_id' => $from->property_id, 'transfer_no' => 'TRF-'.fake()->unique()->numerify('####-#####'), 'from_store_id' => $from->id,
            'to_store_id' => Store::factory()->create(['property_id' => $from->property_id])->id, 'status' => TransferStatus::Dispatched, 'dispatched_at' => now(),
        ];
    }
}
