<?php

namespace Modules\Reservation\Services;

use Modules\Reservation\Enums\ReservationLogAction;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Events\ReservationConfirmed;
use Modules\Reservation\Models\Reservation;

/**
 * Rule 3 of ARCHITECTURE §6.5: a tentative booking is confirmed as soon as what was paid covers
 * the deposit (or the deposit is zero). Called inside the caller's transaction.
 */
class DepositConfirmation
{
    public function __construct(
        private readonly ReservationBalance $balance,
        private readonly ReservationLogger $logger,
    ) {}

    /**
     * @return bool whether the booking was confirmed now
     */
    public function confirmIfMet(Reservation $reservation, ?int $userId = null): bool
    {
        if ($reservation->status !== ReservationStatus::Tentative || ! $this->balance->depositMet($reservation->amount_paid, $reservation->deposit_required)) {
            return false;
        }

        $reservation->forceFill(['status' => ReservationStatus::Confirmed, 'confirmed_at' => now()])->save();
        $reservation->items()->update(['status' => ReservationStatus::Confirmed->value]);
        $this->logger->log($reservation, ReservationLogAction::Confirmed, __('Confirmed: the deposit of :amount is covered.', ['amount' => $reservation->deposit_required]), userId: $userId);

        ReservationConfirmed::dispatch($reservation->tenant_id, $reservation->id);

        return true;
    }
}
