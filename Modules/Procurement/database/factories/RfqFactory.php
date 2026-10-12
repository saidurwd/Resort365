<?php

namespace Modules\Procurement\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Procurement\Enums\RfqStatus;
use Modules\Procurement\Models\PurchaseRequisition;
use Modules\Procurement\Models\Rfq;

/**
 * @extends Factory<Rfq>
 */
class RfqFactory extends Factory
{
    protected $model = Rfq::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $requisition = PurchaseRequisition::factory()->create();

        return ['property_id' => $requisition->property_id, 'rfq_no' => 'RFQ-'.fake()->unique()->numerify('####-#####'), 'purchase_requisition_id' => $requisition->id, 'status' => RfqStatus::Draft, 'created_by' => 1];
    }
}
