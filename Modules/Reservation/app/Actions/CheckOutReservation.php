<?php

namespace Modules\Reservation\Actions;

use App\Support\Actions\Action;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Reservation\Enums\ReservationLogAction;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Exceptions\StayNotPossible;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Services\ReservationLogger;

/**
 * Marks an in-house booking Checked out (ARCHITECTURE §6.4) and releases its room locks. The
 * departure date must be the property's business date or earlier (an early departure, which
 * changes the stay, is Step 2.4). Settling the bill is the caller's job (FrontOffice + Billing).
 */
class CheckOutReservation extends Action
{
    public function __construct(
        private readonly PropertyDirectory $properties,
        private readonly ReservationLogger $logger,
    ) {}

    /**
     * @throws StayNotPossible
     */
    public function handle(int $reservationId, ?int $userId = null): Reservation
    {
        return $this->transaction(function () use ($reservationId, $userId): Reservation {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservationId);

            if ($reservation->status !== ReservationStatus::CheckedIn) {
                throw new StayNotPossible(__('Booking :code is not in house.', ['code' => $reservation->code]));
            }

            $businessDate = $this->properties->find($reservation->property_id)->businessDate ?? now()->toDateString();

            if ($reservation->check_out->toDateString() > $businessDate) {
                throw new StayNotPossible(__('Booking :code leaves on :date. Leaving early changes the stay: shorten it first.', ['code' => $reservation->code, 'date' => $reservation->check_out->format('d M Y')]));
            }

            $reservation->forceFill(['status' => ReservationStatus::CheckedOut, 'checked_out_at' => now()])->save();
            $reservation->items()->update(['status' => ReservationStatus::CheckedOut->value]);
            $reservation->locks()->delete();
            $this->logger->log($reservation, ReservationLogAction::CheckedOut, __('Checked out.'), ['status' => [ReservationStatus::CheckedIn->value, ReservationStatus::CheckedOut->value]], $userId);

            return $reservation;
        });
    }
}
