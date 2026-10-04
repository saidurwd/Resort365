<?php

namespace Modules\Housekeeping\Actions;

use App\Support\Actions\Action;
use Modules\Housekeeping\DTOs\WorkOrderData;
use Modules\Housekeeping\Enums\WorkOrderStatus;
use Modules\Housekeeping\Exceptions\HousekeepingNotPossible;
use Modules\Housekeeping\Models\MaintenanceRequest;

/**
 * Opens a maintenance work order (ARCHITECTURE §5.11): any staff member reports a fault in a room
 * or another place; a preventive schedule opens one when it is due.
 */
class ReportWorkOrder extends Action
{
    /**
     * @throws HousekeepingNotPossible
     */
    public function handle(WorkOrderData $data): MaintenanceRequest
    {
        if ($data->roomId === null && trim((string) $data->location) === '') {
            throw new HousekeepingNotPossible(__('Say which room or where the fault is.'));
        }

        return MaintenanceRequest::query()->create([
            'property_id' => $data->propertyId, 'room_id' => $data->roomId, 'location' => $data->location, 'title' => $data->title,
            'description' => $data->description, 'category' => $data->category, 'priority' => $data->priority,
            'status' => WorkOrderStatus::Open, 'reported_by' => $data->reportedBy, 'assigned_to' => $data->assignedTo,
            'due_on' => $data->dueOn, 'maintenance_schedule_id' => $data->scheduleId, 'labour_cost' => '0.00', 'parts_cost' => '0.00',
        ]);
    }
}
