<?php

namespace Modules\Procurement\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Procurement\Database\Factories\Concerns\ResolvesReferences;
use Modules\Procurement\Models\PurchaseRequisition;
use Modules\Procurement\Models\PurchaseRequisitionLine;

/**
 * @extends Factory<PurchaseRequisitionLine>
 */
class PurchaseRequisitionLineFactory extends Factory
{
    use ResolvesReferences;

    protected $model = PurchaseRequisitionLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $requisition = PurchaseRequisition::factory()->create();

        return ['property_id' => $requisition->property_id, 'purchase_requisition_id' => $requisition->id, 'item_id' => $this->itemId(), 'quantity' => '10', 'estimated_unit_cost' => '100'];
    }
}
