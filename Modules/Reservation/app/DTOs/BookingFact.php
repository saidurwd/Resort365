<?php

namespace Modules\Reservation\DTOs;

use App\Support\DTOs\Data;

/**
 * One booking as the reports see it (Step 7.1). Dates are "Y-m-d" (the booking date in the property's time zone); nights are the
 * nights of the stay and rooms the units booked (a whole cottage counts once), so room nights = nights × rooms. Amounts are
 * decimal strings. `source` and `status` are the values of ReservationSource and ReservationStatus.
 */
final readonly class BookingFact extends Data
{
    public function __construct(
        public int $reservationId,
        public string $code,
        public int $propertyId,
        public string $source,
        public string $status,
        public string $bookedOn,
        public string $checkIn,
        public string $checkOut,
        public int $nights,
        public int $rooms,
        public string $total,
        public string $cancellationFee,
        public ?string $cancelledOn,
        public int $guestId,
        public ?int $companyId,
        public ?int $travelAgentId,
        public int $adults,
        public int $children,
    ) {}

    public function roomNights(): int
    {
        return $this->nights * $this->rooms;
    }

    /** Days between the booking and the arrival, never below zero. */
    public function leadDays(): int
    {
        return max(0, (int) round((strtotime($this->checkIn) - strtotime($this->bookedOn)) / 86400));
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}
