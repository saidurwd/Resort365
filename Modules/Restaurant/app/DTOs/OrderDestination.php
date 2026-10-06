<?php

namespace Modules\Restaurant\DTOs;

use App\Support\DTOs\Data;

/**
 * Where a delivered or staff order goes (ARCHITECTURE §5.10.4): room service to an in-house booking
 * (reservationId), a location delivery to a free-text spot (the pool, the beach, a terrace), a staff meal
 * for a named person or team (guestName).
 */
final readonly class OrderDestination extends Data
{
    public function __construct(
        public ?int $reservationId = null,
        public ?string $location = null,
        public ?string $name = null,
    ) {}
}
