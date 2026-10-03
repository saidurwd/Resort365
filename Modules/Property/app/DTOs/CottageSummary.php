<?php

namespace Modules\Property\DTOs;

use App\Support\DTOs\Data;
use Modules\Property\Enums\BookingMode;

/**
 * A cottage as other modules see it (InventoryCatalog): its booking mode, maximum guests
 * (override or the sum of its active rooms) and the ids of its active rooms.
 */
final readonly class CottageSummary extends Data
{
    /**
     * @param  list<int>  $roomIds  active rooms
     */
    public function __construct(
        public int $id,
        public int $propertyId,
        public int $cottageTypeId,
        public string $code,
        public string $name,
        public BookingMode $bookingMode,
        public int $maxOccupancy,
        public array $roomIds,
        public bool $isActive,
    ) {}

    /**
     * The rate key of its cottage type, e.g. "cottage_type:2".
     */
    public function unitKey(): string
    {
        return 'cottage_type:'.$this->cottageTypeId;
    }
}
