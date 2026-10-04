<?php

namespace Modules\Property\Services;

use Modules\Property\Contracts\RoomStatuses;
use Modules\Property\Enums\HousekeepingStatus;
use Modules\Property\Models\Room;

class RoomStatusesService implements RoomStatuses
{
    public function forProperty(int $propertyId): array
    {
        return Room::query()->where('property_id', $propertyId)->get(['id', 'housekeeping_status'])
            ->mapWithKeys(fn (Room $room): array => [$room->id => $room->housekeeping_status])->all();
    }

    public function set(array $roomIds, HousekeepingStatus $status): array
    {
        $before = [];

        foreach (Room::query()->whereIn('id', $roomIds)->where('housekeeping_status', '!=', $status->value)->get() as $room) {
            $before[$room->id] = $room->housekeeping_status;
            $room->forceFill(['housekeeping_status' => $status])->save();
        }

        return $before;
    }
}
