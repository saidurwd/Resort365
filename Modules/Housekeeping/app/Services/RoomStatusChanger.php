<?php

namespace Modules\Housekeeping\Services;

use Modules\Housekeeping\Models\RoomStatusLog;
use Modules\Property\Contracts\RoomStatuses;
use Modules\Property\Enums\HousekeepingStatus;

/**
 * Changes rooms' cleaning status through Property's RoomStatuses and writes room_status_logs for
 * every room that actually changed. Called inside the caller's transaction.
 */
class RoomStatusChanger
{
    public function __construct(
        private readonly RoomStatuses $statuses,
    ) {}

    /**
     * @param  list<int>  $roomIds
     * @return int how many rooms changed
     */
    public function change(int $propertyId, array $roomIds, HousekeepingStatus $status, string $reason, ?int $userId = null): int
    {
        $before = $this->statuses->set($roomIds, $status);

        foreach ($before as $roomId => $from) {
            RoomStatusLog::query()->create([
                'property_id' => $propertyId, 'room_id' => $roomId, 'from_status' => $from, 'to_status' => $status,
                'reason' => mb_substr($reason, 0, 190), 'changed_by' => $userId,
            ]);
        }

        return count($before);
    }
}
