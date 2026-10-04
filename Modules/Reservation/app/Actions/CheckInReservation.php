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
 * Marks a booking Checked in (ARCHITECTURE §6.4): only a confirmed booking (deposit paid or
 * waived) whose arrival date is the property's business date or earlier.
 */
class CheckInReservation extends Action
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

            if ($reservation->status !== ReservationStatus::Confirmed) {
                throw new StayNotPossible($reservation->status === ReservationStatus::Tentative
                    ? __('Booking :code is still tentative: take the deposit first.', ['code' => $reservation->code])
                    : __('Booking :code is :status.', ['code' => $reservation->code, 'status' => strtolower($reservation->status->label())]));
            }

            $businessDate = $this->properties->find($reservation->property_id)->businessDate ?? now()->toDateString();

            if ($reservation->check_in->toDateString() > $businessDate) {
                throw new StayNotPossible(__('Booking :code arrives on :date; it cannot be checked in before then.', ['code' => $reservation->code, 'date' => $reservation->check_in->format('d M Y')]));
            }

            $reservation->forceFill(['status' => ReservationStatus::CheckedIn, 'checked_in_at' => now()])->save();
            $reservation->items()->update(['status' => ReservationStatus::CheckedIn->value]);
            $this->logger->log($reservation, ReservationLogAction::CheckedIn, __('Checked in.'), ['status' => [ReservationStatus::Confirmed->value, ReservationStatus::CheckedIn->value]], $userId);

            return $reservation;
        });
    }
}
