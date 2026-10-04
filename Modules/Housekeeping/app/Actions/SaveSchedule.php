<?php

namespace Modules\Housekeeping\Actions;

use App\Support\Actions\Action;
use Modules\Housekeeping\Models\MaintenanceSchedule;

/**
 * Creates or changes a preventive maintenance schedule of a property.
 */
class SaveSchedule extends Action
{
    /**
     * @param  array{title: string, category: string, room_id?: int|null, location?: string|null, interval_days: int, next_due_on: string, assigned_to?: int|null, is_active?: bool, notes?: string|null}  $data
     */
    public function handle(int $propertyId, ?MaintenanceSchedule $schedule, array $data): MaintenanceSchedule
    {
        $schedule ??= new MaintenanceSchedule(['property_id' => $propertyId]);
        $schedule->fill([
            'title' => $data['title'], 'category' => $data['category'], 'room_id' => $data['room_id'] ?? null, 'location' => $data['location'] ?? null,
            'interval_days' => $data['interval_days'], 'next_due_on' => $data['next_due_on'], 'assigned_to' => $data['assigned_to'] ?? null,
            'is_active' => $data['is_active'] ?? true, 'notes' => $data['notes'] ?? null,
        ])->save();

        return $schedule;
    }
}
