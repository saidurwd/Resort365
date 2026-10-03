<?php

namespace Modules\Billing\Services;

use Illuminate\Contracts\View\View;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Models\Payment;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Reservation\DTOs\ReservationSummary;
use Modules\Reservation\Enums\ReservationStatus;

/**
 * The Payments tab of the reservation page (registered through Reservation's ReservationTabs):
 * the payments received and a form to take one. The form suggests what is still needed for the
 * deposit, or else the balance.
 */
class PaymentsTab
{
    public function __construct(
        private readonly ReservationLookup $reservations,
        private readonly PropertyDirectory $properties,
    ) {}

    public function render(int $reservationId): View|string
    {
        $reservation = $this->reservations->find($reservationId);

        if (! $reservation instanceof ReservationSummary) {
            return '';
        }

        return view('billing::payments.tab', [
            'reservation' => $reservation,
            'payments' => Payment::query()->where('reservation_id', $reservationId)->orderBy('received_at')->orderBy('id')->get(),
            'methods' => PaymentMethod::cases(),
            'suggested' => $this->suggested($reservation),
            'timezone' => $this->properties->find($reservation->propertyId)->timezone ?? 'UTC',
        ]);
    }

    /**
     * What is still needed for the deposit of a tentative booking, else the balance.
     */
    private function suggested(ReservationSummary $reservation): string
    {
        if ($reservation->status === ReservationStatus::Tentative) {
            $short = bcsub($reservation->depositRequired, $reservation->amountPaid, 2);

            if (bccomp($short, '0', 2) > 0) {
                return $short;
            }
        }

        return $reservation->balanceDue;
    }
}
