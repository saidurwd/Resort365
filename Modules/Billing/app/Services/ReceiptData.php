<?php

namespace Modules\Billing\Services;

use Modules\Billing\Models\Payment;
use Modules\Guest\Contracts\GuestLookup;
use Modules\IAM\Contracts\UserDirectory;
use Modules\IAM\DTOs\UserSummary;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Reservation\DTOs\ReservationSummary;

/**
 * What a payment receipt shows: the property, the reservation and its guest, who received it.
 */
class ReceiptData
{
    public function __construct(
        private readonly PropertyDirectory $properties,
        private readonly ReservationLookup $reservations,
        private readonly GuestLookup $guests,
        private readonly UserDirectory $users,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(Payment $payment): array
    {
        $property = $this->properties->find($payment->property_id);
        $reservation = $payment->reservation_id !== null ? $this->reservations->find($payment->reservation_id) : null;
        $receiver = collect($this->users->all())->first(fn (UserSummary $user): bool => $user->id === $payment->received_by);

        return [
            'payment' => $payment,
            'property' => $property,
            'reservation' => $reservation,
            'guest' => $reservation instanceof ReservationSummary ? $this->guests->find($reservation->primaryGuestId) : null,
            'receivedBy' => $receiver?->name,
            'timezone' => $property->timezone ?? 'UTC',
        ];
    }
}
