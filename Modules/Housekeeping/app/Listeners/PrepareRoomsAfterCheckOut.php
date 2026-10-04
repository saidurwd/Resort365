<?php

namespace Modules\Housekeeping\Listeners;

use Modules\FrontOffice\Events\GuestCheckedOut;
use Modules\Housekeeping\Actions\PrepareRoomsAfterDeparture;

/**
 * Check-out (ARCHITECTURE §4.5): the rooms the guest left become Dirty with a departure clean.
 */
class PrepareRoomsAfterCheckOut
{
    public function __construct(
        private readonly PrepareRoomsAfterDeparture $prepare,
    ) {}

    public function handle(GuestCheckedOut $event): void
    {
        $this->prepare->handle($event->propertyId, array_map(intval(...), $event->roomIds), $event->reservationId, __('Guest checked out'),
            auth()->id() !== null ? (int) auth()->id() : null);
    }
}
