<?php

namespace Modules\Reservation\DTOs;

use App\Support\DTOs\Data;
use Modules\Reservation\Enums\ItemType;

/**
 * One item to book: a room (unitId = room id) or a whole cottage (unitId = cottage id).
 */
final readonly class BookingItem extends Data
{
    public function __construct(
        public ItemType $type,
        public int $unitId,
        public int $ratePlanId,
        public int $adults,
        public int $children = 0,
    ) {}
}
