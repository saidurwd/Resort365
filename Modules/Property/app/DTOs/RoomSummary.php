<?php

namespace Modules\Property\DTOs;

use App\Support\DTOs\Data;

/**
 * A room as other modules see it (InventoryCatalog), with its capacity worked out
 * (OccupancyCalculator) and its room type's base occupancy (the guests the rate covers).
 */
final readonly class RoomSummary extends Data
{
    public function __construct(
        public int $id,
        public int $propertyId,
        public int $cottageId,
        public int $roomTypeId,
        public string $number,
        public ?string $name,
        public int $baseOccupancy,
        public int $maxAdults,
        public int $maxChildren,
        public int $maxOccupancy,
        public bool $isActive,
    ) {}

    /**
     * The rate key of its room type, e.g. "room_type:4".
     */
    public function unitKey(): string
    {
        return 'room_type:'.$this->roomTypeId;
    }
}
