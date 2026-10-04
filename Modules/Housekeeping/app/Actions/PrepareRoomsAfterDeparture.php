<?php

namespace Modules\Housekeeping\Actions;

use App\Support\Actions\Action;
use Modules\Housekeeping\Enums\TaskType;
use Modules\Housekeeping\Services\RoomStatusChanger;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Property\Enums\HousekeepingStatus;

/**
 * Rooms a guest has left (check-out, or the old room of a room move) become Dirty and get a
 * departure clean for the business date (ARCHITECTURE §5.11, the GuestCheckedOut reaction of §4.5).
 */
class PrepareRoomsAfterDeparture extends Action
{
    public function __construct(
        private readonly RoomStatusChanger $statuses,
        private readonly CreateTask $tasks,
        private readonly PropertyDirectory $properties,
    ) {}

    /**
     * @param  list<int>  $roomIds
     */
    public function handle(int $propertyId, array $roomIds, ?int $reservationId, string $reason, ?int $userId = null): void
    {
        $date = $this->properties->find($propertyId)->businessDate ?? now()->toDateString();

        $this->transaction(function () use ($propertyId, $roomIds, $reservationId, $reason, $userId, $date): void {
            $this->statuses->change($propertyId, $roomIds, HousekeepingStatus::Dirty, $reason, $userId);

            foreach ($roomIds as $roomId) {
                $this->tasks->handle($propertyId, $roomId, $date, TaskType::Departure, $reservationId);
            }
        });
    }
}
