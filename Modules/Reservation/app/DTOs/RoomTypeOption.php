<?php

namespace Modules\Reservation\DTOs;

use App\Support\DTOs\Data;
use Modules\Property\DTOs\RoomSummary;
use Modules\Property\DTOs\UnitTypeSummary;

/**
 * The free rooms of one room type for the stay, with the price of one room (null = no rate) and
 * any restrictions that stop it being sold.
 */
final readonly class RoomTypeOption extends Data
{
    /**
     * @param  list<RoomSummary>  $rooms
     * @param  list<RestrictionViolation>  $violations
     */
    public function __construct(
        public UnitTypeSummary $roomType,
        public array $rooms,
        public bool $fits,
        public ?PriceQuote $quote,
        public array $violations,
    ) {}

    public function isBookable(): bool
    {
        return $this->quote instanceof PriceQuote && $this->violations === [];
    }
}
