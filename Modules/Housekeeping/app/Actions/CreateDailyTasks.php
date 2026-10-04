<?php

namespace Modules\Housekeeping\Actions;

use App\Support\Actions\Action;
use Modules\Housekeeping\Enums\TaskType;
use Modules\Housekeeping\Services\RoomStatusChanger;
use Modules\Property\Enums\HousekeepingStatus;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Reservation\DTOs\RoomOccupancy;

/**
 * The daily round (ARCHITECTURE §5.11): every room in house on the business date becomes Dirty and
 * gets a stayover task. Run after each night audit and from the task list; safe to repeat (a room
 * already having its stayover task for the date is left alone).
 *
 * @return int how many rooms are on the round
 */
class CreateDailyTasks extends Action
{
    public function __construct(
        private readonly ReservationLookup $reservations,
        private readonly RoomStatusChanger $statuses,
        private readonly CreateTask $tasks,
    ) {}

    public function handle(int $propertyId, string $businessDate, ?int $userId = null): int
    {
        $occupied = array_filter($this->reservations->roomOccupancy($propertyId, $businessDate), fn (RoomOccupancy $room): bool => $room->occupied);

        return $this->transaction(function () use ($propertyId, $businessDate, $userId, $occupied): int {
            foreach ($occupied as $room) {
                $task = $this->tasks->handle($propertyId, $room->roomId, $businessDate, TaskType::Stayover, $room->reservationId);

                if ($task->wasRecentlyCreated) {
                    $this->statuses->change($propertyId, [$room->roomId], HousekeepingStatus::Dirty, __('Stayover: guest in house'), $userId);
                }
            }

            return count($occupied);
        });
    }
}
