<?php

namespace Modules\Rates\DTOs;

use App\Support\DTOs\Data;

/**
 * The restrictions in force on one date for one rate plan and type (the strictest of all rows).
 */
final readonly class RestrictionSet extends Data
{
    public function __construct(
        public ?int $minStay = null,
        public ?int $maxStay = null,
        public bool $closedToArrival = false,
        public bool $closedToDeparture = false,
        public bool $stopSell = false,
    ) {}

    public function isEmpty(): bool
    {
        return $this->minStay === null && $this->maxStay === null && ! $this->closedToArrival && ! $this->closedToDeparture && ! $this->stopSell;
    }

    /**
     * Combine with another row: the longer minimum, the shorter maximum, any closure.
     */
    public function merge(self $other): self
    {
        return new self(
            minStay: $this->minStay === null ? $other->minStay : max($this->minStay, $other->minStay ?? 0),
            maxStay: $this->maxStay === null ? $other->maxStay : min($this->maxStay, $other->maxStay ?? PHP_INT_MAX),
            closedToArrival: $this->closedToArrival || $other->closedToArrival,
            closedToDeparture: $this->closedToDeparture || $other->closedToDeparture,
            stopSell: $this->stopSell || $other->stopSell,
        );
    }
}
