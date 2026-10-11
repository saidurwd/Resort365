<?php

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Enums\RequisitionStatus;
use Modules\Inventory\Models\StockRequisition;
use Modules\Inventory\Models\Store;

/**
 * @extends Factory<StockRequisition>
 */
class StockRequisitionFactory extends Factory
{
    protected $model = StockRequisition::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $store = Store::factory()->create();

        return ['property_id' => $store->property_id, 'requisition_no' => 'SR-'.fake()->unique()->numerify('####-#####'), 'store_id' => $store->id, 'department_id' => 1, 'status' => RequisitionStatus::Pending, 'requested_by' => 1, 'estimated_value' => '100'];
    }
}
