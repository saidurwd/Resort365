<?php

namespace Modules\Reservation\Actions;

use App\Support\Actions\Action;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Reservation\Enums\ReservationLogAction;
use Modules\Reservation\Exceptions\ReservationNotChangeable;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationGuest;
use Modules\Reservation\Services\ReservationLogger;

/**
 * Makes one of a reservation's guests its primary guest (the booker, shown on the booking).
 */
class MakePrimaryGuest extends Action
{
    public function __construct(
        private readonly GuestLookup $guests,
        private readonly ReservationLogger $logger,
    ) {}

    /**
     * @throws ReservationNotChangeable
     */
    public function handle(Reservation $reservation, ReservationGuest $guest): Reservation
    {
        if (! $reservation->isChangeable()) {
            throw new ReservationNotChangeable(__('Only tentative and confirmed bookings can be changed.'));
        }

        return $this->transaction(function () use ($reservation, $guest): Reservation {
            $previous = $reservation->primary_guest_id;
            $reservation->guests()->where('id', '!=', $guest->id)->update(['is_primary' => false]);
            $guest->forceFill(['is_primary' => true])->save();
            $reservation->forceFill(['primary_guest_id' => $guest->guest_id])->save();

            $this->logger->log($reservation, ReservationLogAction::PrimaryGuestChanged, __(':name is now the primary guest.', ['name' => $this->guests->find($guest->guest_id)->name ?? '#'.$guest->guest_id]),
                ['primary_guest_id' => [$previous, $guest->guest_id]]);

            return $reservation;
        });
    }
}
