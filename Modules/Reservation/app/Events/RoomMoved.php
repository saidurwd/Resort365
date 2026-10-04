<?php

namespace Modules\Reservation\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An in-house guest moved from one room to another (MoveRoom): the old room was slept in and is
 * free from tonight (Housekeeping marks it dirty), the new room is occupied.
 */
class RoomMoved implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $propertyId,
        public readonly int $reservationId,
        public readonly int $fromRoomId,
        public readonly int $toRoomId,
    ) {}
}
