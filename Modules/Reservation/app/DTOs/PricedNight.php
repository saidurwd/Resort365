<?php

namespace Modules\Reservation\DTOs;

use App\Support\DTOs\Data;

/**
 * One priced night of an item, as it will be snapshotted in reservation_item_nights (Step 1.6).
 * net = base + extras − discount (without tax); total = net + tax. Decimal strings.
 */
final readonly class PricedNight extends Data
{
    public function __construct(
        public string $date,
        public string $base,
        public string $extras,
        public string $discount,
        public string $net,
        public string $tax,
        public string $total,
        public string $source,
        public ?string $seasonName = null,
    ) {}
}
