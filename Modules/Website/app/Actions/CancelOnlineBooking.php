<?php

namespace Modules\Website\Actions;

use App\Support\Actions\Action;
use Modules\Reservation\Contracts\BookingEngine;
use Modules\Reservation\DTOs\ReservationSummary;
use Modules\Reservation\Exceptions\ReservationNotChangeable;
use Modules\Website\Models\WebsiteBooking;

/**
 * A guest cancels their website booking from its manage link (Step 8.2b), within the booking's cancellation policy (the fee is shown
 * before they confirm).
 */
class CancelOnlineBooking extends Action
{
    public function __construct(private readonly BookingEngine $engine) {}

    /**
     * @throws ReservationNotChangeable
     */
    public function handle(WebsiteBooking $booking): ReservationSummary
    {
        return $this->engine->cancel($booking->reservation_id, __('Cancelled by the guest on the website.'));
    }
}
