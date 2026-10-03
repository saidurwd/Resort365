<?php

namespace Modules\Reservation\Actions;

use App\Support\Actions\Action;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Guest\DTOs\GuestSummary;
use Modules\Reservation\Enums\ReservationLogAction;
use Modules\Reservation\Exceptions\BookingNotPossible;
use Modules\Reservation\Exceptions\ReservationNotChangeable;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationGuest;
use Modules\Reservation\Services\ReservationLogger;

/**
 * Adds a guest (an occupant) to a reservation, optionally to one of its rooms or cottages.
 * Blacklisted guests and guests already on the booking are refused.
 */
class AddReservationGuest extends Action
{
    public function __construct(
        private readonly GuestLookup $guests,
        private readonly ReservationLogger $logger,
    ) {}

    /**
     * @throws BookingNotPossible|ReservationNotChangeable
     */
    public function handle(Reservation $reservation, int $guestId, ?int $itemId = null): ReservationGuest
    {
        if (! $reservation->isChangeable()) {
            throw new ReservationNotChangeable(__('Only tentative and confirmed bookings can be changed.'));
        }

        $guest = $this->guests->find($guestId);

        if (! $guest instanceof GuestSummary) {
            throw new BookingNotPossible(__('Unknown guest.'));
        }

        if ($guest->isBlacklisted) {
            throw new BookingNotPossible(__(':name is blacklisted and cannot be added.', ['name' => $guest->name]));
        }

        if ($reservation->guests()->where('guest_id', $guest->id)->exists()) {
            throw new BookingNotPossible(__(':name is already on this booking.', ['name' => $guest->name]));
        }

        return $this->transaction(function () use ($reservation, $guest, $itemId): ReservationGuest {
            $added = $reservation->guests()->create(['property_id' => $reservation->property_id, 'guest_id' => $guest->id, 'reservation_item_id' => $itemId, 'is_primary' => false]);
            $this->logger->log($reservation, ReservationLogAction::GuestAdded, __(':name added.', ['name' => $guest->name]), ['guest_id' => [null, $guest->id]]);

            return $added;
        });
    }
}
