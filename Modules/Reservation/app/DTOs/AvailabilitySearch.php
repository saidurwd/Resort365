<?php

namespace Modules\Reservation\DTOs;

use App\Support\DTOs\Data;
use Carbon\CarbonImmutable;

/**
 * An availability search: a property's stay [checkIn, checkOut) for a party in a rate plan, through a sales channel (`front_desk`
 * for staff and the API, `online` for the public website, Step 8.2b): a plan not sold through it cannot be booked.
 */
final readonly class AvailabilitySearch extends Data
{
    public function __construct(
        public int $propertyId,
        public int $ratePlanId,
        public CarbonImmutable $checkIn,
        public CarbonImmutable $checkOut,
        public Occupancy $occupancy,
        public ?string $promoCode = null,
        public string $channel = 'front_desk',
    ) {}

    public function nights(): int
    {
        return (int) $this->checkIn->diffInDays($this->checkOut);
    }
}
