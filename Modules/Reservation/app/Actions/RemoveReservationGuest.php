<?php

namespace Modules\Reservation\Actions;

use App\Support\Actions\Action;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Reservation\Enums\ReservationLogAction;
use Modules\Reservation\Exceptions\BookingNotPossible;
use Modules\Reservation\Exceptions\ReservationNotChangeable;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationGuest;
use Modules\Reservation\Services\ReservationLogger;

/**
 * Removes a guest from a reservation. The primary guest (the booker) stays: make another guest
 * primary first.
 */
class RemoveReservationGuest extends Action
{
    public function __construct(
        private readonly GuestLookup $guests,
        private readonly ReservationLogger $logger,
    ) {}

    /**
     * @throws BookingNotPossible|ReservationNotChangeable
     */
    public function handle(Reservation $reservation, ReservationGuest $guest): void
    {
        if (! $reservation->isChangeable()) {
            throw new ReservationNotChangeable(__('Only tentative and confirmed bookings can be changed.'));
        }

        if ($guest->is_primary) {
            throw new BookingNotPossible(__('The primary guest cannot be removed. Make another guest primary first.'));
        }

        $this->transaction(function () use ($reservation, $guest): void {
            $guest->delete();
            $this->logger->log($reservation, ReservationLogAction::GuestRemoved, __(':name removed.', ['name' => $this->guests->find($guest->guest_id)->name ?? '#'.$guest->guest_id]),
                ['guest_id' => [$guest->guest_id, null]]);
        });
    }
}
