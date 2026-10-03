<?php

namespace Modules\Property\DTOs;

use App\Support\DTOs\Data;
use Modules\Property\Enums\UnitKind;

/**
 * A room type or cottage type as other modules see it (InventoryCatalog).
 * For a cottage type, baseOccupancy is its max occupancy and the adult/child limits are null.
 */
final readonly class UnitTypeSummary extends Data
{
    public function __construct(
        public UnitKind $kind,
        public int $id,
        public int $propertyId,
        public string $code,
        public string $name,
        public int $baseOccupancy,
        public int $maxOccupancy,
        public ?int $maxAdults,
        public ?int $maxChildren,
        public bool $isActive,
    ) {}

    /**
     * A stable key for maps and form fields, e.g. "room_type:4".
     */
    public function key(): string
    {
        return $this->kind->value.':'.$this->id;
    }
}
