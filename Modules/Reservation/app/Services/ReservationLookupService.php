<?php

namespace Modules\Reservation\Services;

use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Reservation\DTOs\ReservationSummary;
use Modules\Reservation\Models\Reservation;

class ReservationLookupService implements ReservationLookup
{
    public function find(int $reservationId): ?ReservationSummary
    {
        $reservation = Reservation::query()->find($reservationId);

        return $reservation instanceof Reservation ? new ReservationSummary(
            $reservation->id, $reservation->property_id, $reservation->code, $reservation->status, $reservation->payment_status,
            $reservation->primary_guest_id, $reservation->check_in->toDateString(), $reservation->check_out->toDateString(),
            $reservation->currency_code, $reservation->grand_total, $reservation->deposit_required, $reservation->amount_paid,
            $reservation->balance_due, $reservation->cancellation_fee,
        ) : null;
    }
}
