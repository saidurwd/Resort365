<?php

namespace Modules\Reservation\DTOs;

use App\Support\DTOs\Data;

/**
 * One priced item of a booking, with the rooms it locks.
 *
 * @param  list<int>  $roomIds
 */
final readonly class QuotedItem extends Data
{
    /**
     * @param  list<int>  $roomIds
     */
    public function __construct(
        public BookingItem $item,
        public PriceQuote $quote,
        public string $label,
        public int $cottageId,
        public ?int $roomTypeId,
        public ?int $cottageTypeId,
        public array $roomIds,
    ) {}
}
