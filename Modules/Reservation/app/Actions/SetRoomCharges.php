<?php

namespace Modules\Reservation\Actions;

use App\Support\Actions\Action;
use Modules\Reservation\Enums\ReservationLogAction;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Services\ReservationLogger;

/**
 * Allows or blocks charges from the restaurant and other outlets to a booking's folio ("no room charges",
 * ARCHITECTURE §5.10.8), and notes it in the booking's history.
 */
class SetRoomCharges extends Action
{
    public function __construct(
        private readonly ReservationLogger $logger,
    ) {}

    public function handle(Reservation $reservation, bool $blocked, ?int $userId = null): Reservation
    {
        if ($reservation->no_room_charges === $blocked) {
            return $reservation;
        }

        return $this->transaction(function () use ($reservation, $blocked, $userId): Reservation {
            $reservation->forceFill(['no_room_charges' => $blocked])->save();
            $this->logger->log($reservation, ReservationLogAction::RoomChargesChanged,
                $blocked ? __('Outlets may no longer charge this booking.') : __('Outlets may charge this booking again.'),
                ['no_room_charges' => ['old' => ! $blocked, 'new' => $blocked]], $userId);

            return $reservation;
        });
    }
}
