<?php

namespace Modules\Housekeeping\Listeners;

use Modules\Housekeeping\Actions\PrepareRoomsAfterDeparture;
use Modules\Reservation\Events\RoomMoved;

/**
 * An in-house room move: the room the guest left becomes Dirty with a departure clean.
 */
class PrepareRoomAfterMove
{
    public function __construct(
        private readonly PrepareRoomsAfterDeparture $prepare,
    ) {}

    public function handle(RoomMoved $event): void
    {
        $this->prepare->handle($event->propertyId, [$event->fromRoomId], $event->reservationId, __('Guest moved to another room'),
            auth()->id() !== null ? (int) auth()->id() : null);
    }
}
