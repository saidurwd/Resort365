<?php

namespace Modules\Reservation\DTOs;

use App\Support\DTOs\Data;
use Carbon\CarbonImmutable;

/**
 * A new stay for an existing reservation (ModifyReservation): dates and the full list of items
 * (rooms or whole cottages, each with its rate plan and occupancy). Items left out are removed.
 */
final readonly class ReservationChange extends Data
{
    /**
     * @param  list<BookingItem>  $items
     */
    public function __construct(
        public CarbonImmutable $checkIn,
        public CarbonImmutable $checkOut,
        public array $items,
    ) {}
}
