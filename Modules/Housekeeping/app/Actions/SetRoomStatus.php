<?php

namespace Modules\Housekeeping\Actions;

use App\Support\Actions\Action;
use Modules\Housekeeping\Services\RoomStatusChanger;
use Modules\Property\Enums\HousekeepingStatus;

/**
 * Sets rooms' cleaning status by hand from the room status board (bulk update).
 */
class SetRoomStatus extends Action
{
    public function __construct(
        private readonly RoomStatusChanger $statuses,
    ) {}

    /**
     * @param  list<int>  $roomIds
     * @return int how many rooms changed
     */
    public function handle(int $propertyId, array $roomIds, HousekeepingStatus $status, ?int $userId = null): int
    {
        return $this->transaction(fn (): int => $this->statuses->change($propertyId, $roomIds, $status, __('Set on the room status board'), $userId));
    }
}
