<?php

namespace Modules\Reservation\DTOs;

use App\Support\DTOs\Data;

/**
 * One booked night of one room or cottage, as priced at booking (reservation_item_nights), for
 * posting to a folio (check-out now, night audit later). Amounts are decimal strings; date Y-m-d.
 * taxCategoryId is the rate plan's (for the tax breakdown on the invoice).
 */
final readonly class RoomNightCharge extends Data
{
    public function __construct(
        public int $nightId,
        public string $date,
        public string $label,
        public string $net,
        public string $tax,
        public string $total,
        public ?int $taxCategoryId,
        public string $mealComponent = '0.00',
    ) {}
}
