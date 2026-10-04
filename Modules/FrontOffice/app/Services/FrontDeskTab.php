<?php

namespace Modules\FrontOffice\Services;

use Illuminate\Contracts\View\View;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Reservation\DTOs\ReservationSummary;

/**
 * The reservation page's Front desk tab: where the stay stands and the way to check in.
 */
class FrontDeskTab
{
    public function __construct(private readonly ReservationLookup $reservations) {}

    public function render(int $reservationId): View|string
    {
        $reservation = $this->reservations->find($reservationId);

        return $reservation instanceof ReservationSummary ? view('frontoffice::check-in.tab', ['reservation' => $reservation]) : '';
    }
}
