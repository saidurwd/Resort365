<?php

namespace Modules\Housekeeping\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Housekeeping\Database\Factories\Concerns\ResolvesRoom;
use Modules\Housekeeping\Enums\WorkOrderCategory;
use Modules\Housekeeping\Enums\WorkOrderPriority;
use Modules\Housekeeping\Enums\WorkOrderStatus;
use Modules\Housekeeping\Models\MaintenanceRequest;

/**
 * @extends Factory<MaintenanceRequest>
 */
class MaintenanceRequestFactory extends Factory
{
    use ResolvesRoom;

    protected $model = MaintenanceRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'location' => 'Lobby',
            'title' => 'Light flickering',
            'description' => 'The lobby ceiling light flickers in the evening.',
            'category' => WorkOrderCategory::Electrical,
            'priority' => WorkOrderPriority::Normal,
            'status' => WorkOrderStatus::Open,
            'labour_cost' => '0.00',
            'parts_cost' => '0.00',
        ];
    }
}
