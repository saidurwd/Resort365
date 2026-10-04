<?php

namespace Modules\Housekeeping\Actions;

use App\Support\Actions\Action;
use Illuminate\Database\UniqueConstraintViolationException;
use Modules\Housekeeping\Enums\TaskStatus;
use Modules\Housekeeping\Enums\TaskType;
use Modules\Housekeeping\Models\HousekeepingTask;
use RuntimeException;

/**
 * Creates a room's cleaning task for a business date, or returns the one that exists (one per room,
 * date and type), so check-out, the night audit and the "create today's tasks" button never
 * duplicate a task. A finished task that is asked for again is reopened.
 */
class CreateTask extends Action
{
    public function handle(int $propertyId, int $roomId, string $businessDate, TaskType $type, ?int $reservationId = null): HousekeepingTask
    {
        $find = fn (): ?HousekeepingTask => HousekeepingTask::query()->where('room_id', $roomId)->where('business_date', $businessDate)->where('type', $type->value)->first();
        $existing = $find();

        if ($existing instanceof HousekeepingTask) {
            if ($type === TaskType::Departure && in_array($existing->status, [TaskStatus::Done, TaskStatus::Inspected, TaskStatus::Skipped], true)) {
                $existing->forceFill(['status' => TaskStatus::Pending, 'started_at' => null, 'finished_at' => null, 'inspected_at' => null, 'inspected_by' => null])->save();
            }

            return $existing;
        }

        try {
            return HousekeepingTask::query()->create([
                'property_id' => $propertyId, 'room_id' => $roomId, 'business_date' => $businessDate, 'type' => $type,
                'status' => TaskStatus::Pending, 'reservation_id' => $reservationId,
            ]);
        } catch (UniqueConstraintViolationException) {
            return $find() ?? throw new RuntimeException('Task vanished.');
        }
    }
}
