<?php

namespace Modules\Procurement\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Procurement\Database\Factories\Concerns\ResolvesReferences;
use Modules\Procurement\Enums\PurchaseRequisitionStatus;
use Modules\Procurement\Enums\RequisitionSource;
use Modules\Procurement\Models\PurchaseRequisition;

/**
 * @extends Factory<PurchaseRequisition>
 */
class PurchaseRequisitionFactory extends Factory
{
    use ResolvesReferences;

    protected $model = PurchaseRequisition::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['property_id' => $this->propertyId(), 'requisition_no' => 'PR-'.fake()->unique()->numerify('####-#####'), 'status' => PurchaseRequisitionStatus::Pending, 'source' => RequisitionSource::Manual, 'requested_by' => 1, 'estimated_value' => '1000'];
    }
}
