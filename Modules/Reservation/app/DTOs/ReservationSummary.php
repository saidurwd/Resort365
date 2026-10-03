<?php

namespace Modules\Reservation\DTOs;

use App\Support\DTOs\Data;
use Modules\Reservation\Enums\PaymentStatus;
use Modules\Reservation\Enums\ReservationStatus;

/**
 * A reservation as other modules see it (ReservationLookup). Amounts are decimal strings in the
 * reservation's currency; dates are Y-m-d.
 */
final readonly class ReservationSummary extends Data
{
    public function __construct(
        public int $id,
        public int $propertyId,
        public string $code,
        public ReservationStatus $status,
        public PaymentStatus $paymentStatus,
        public int $primaryGuestId,
        public string $checkIn,
        public string $checkOut,
        public string $currencyCode,
        public string $grandTotal,
        public string $depositRequired,
        public string $amountPaid,
        public string $balanceDue,
        public ?string $cancellationFee,
    ) {}

    /**
     * Whether money may still be taken for it (not cancelled, checked out or a no-show).
     */
    public function acceptsPayments(): bool
    {
        return in_array($this->status, [ReservationStatus::Tentative, ReservationStatus::Confirmed, ReservationStatus::CheckedIn], true);
    }
}
