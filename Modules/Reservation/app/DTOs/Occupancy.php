<?php

namespace Modules\Reservation\DTOs;

use App\Support\DTOs\Data;

/**
 * Guests of a booking item.
 */
final readonly class Occupancy extends Data
{
    public function __construct(
        public int $adults,
        public int $children = 0,
    ) {}

    public function total(): int
    {
        return $this->adults + $this->children;
    }
}
