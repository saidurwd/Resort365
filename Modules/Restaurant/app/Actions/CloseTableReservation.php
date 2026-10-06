<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Modules\Restaurant\Enums\TableReservationStatus;
use Modules\Restaurant\Exceptions\RestaurantSetupInvalid;
use Modules\Restaurant\Models\TableReservation;

/**
 * Ends a booked reservation without seating it (ARCHITECTURE §5.10.12): cancelled by the customer or the
 * restaurant, or a no-show. Seated and finished reservations do not change.
 */
class CloseTableReservation extends Action
{
    /**
     * @throws RestaurantSetupInvalid
     */
    public function handle(TableReservation $reservation, TableReservationStatus $to): TableReservation
    {
        if (! in_array($to, [TableReservationStatus::Cancelled, TableReservationStatus::NoShow], true)) {
            throw new RestaurantSetupInvalid(__('A reservation is cancelled or marked a no-show.'));
        }

        if ($reservation->status !== TableReservationStatus::Booked) {
            throw new RestaurantSetupInvalid($to === TableReservationStatus::NoShow
                ? __('Only a booked reservation can be marked as a no-show.')
                : __('Only a booked reservation can be cancelled.'));
        }

        $reservation->forceFill(['status' => $to])->save();

        return $reservation;
    }
}
