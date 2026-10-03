<?php

namespace Modules\Property\DTOs;

use App\Support\DTOs\Data;

/**
 * How many guests a room takes (OccupancyCalculator).
 */
final readonly class RoomCapacity extends Data
{
    public function __construct(
        public int $maxAdults,
        public int $maxChildren,
        public int $maxOccupancy,
    ) {}
}
