<?php

namespace Modules\Reservation\DTOs;

use App\Support\DTOs\Data;
use Carbon\CarbonImmutable;
use Modules\Reservation\Enums\ReservationSource;

/**
 * Everything CreateReservation needs. depositPercent null = the deposit policy's default;
 * allowDepositOverride lets the percent go outside the policy limits (reservation.deposit.override).
 */
final readonly class NewReservation extends Data
{
    /**
     * @param  list<BookingItem>  $items
     */
    public function __construct(
        public int $propertyId,
        public CarbonImmutable $checkIn,
        public CarbonImmutable $checkOut,
        public array $items,
        public int $primaryGuestId,
        public ReservationSource $source = ReservationSource::FrontDesk,
        public ?int $companyId = null,
        public ?int $travelAgentId = null,
        public ?string $depositPercent = null,
        public ?string $promoCode = null,
        public ?string $specialRequests = null,
        public ?string $internalNotes = null,
        public ?int $createdBy = null,
        public bool $allowDepositOverride = false,
    ) {}
}
