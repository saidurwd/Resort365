<?php

namespace Modules\Reservation\Services;

use Modules\Property\Contracts\RoomUsage;
use Modules\Reservation\Enums\LockType;
use Modules\Reservation\Models\InventoryLock;

/**
 * Property's room-deletion check: a room is still needed while it has reservation or hold
 * locks from today on. (Out-of-order and owner blocks do not stop a deletion.)
 */
class LockedRoomUsage implements RoomUsage
{
    public function hasFutureBookings(int $roomId): bool
    {
        return InventoryLock::query()->where('room_id', $roomId)->where('stay_date', '>=', now()->toDateString())
            ->whereIn('lock_type', [LockType::Reservation->value, LockType::Hold->value])->exists();
    }
}
