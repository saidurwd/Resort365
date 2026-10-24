<?php

namespace Modules\Property\DTOs;

use App\Support\DTOs\Data;
use Modules\Property\Enums\UnitKind;

/**
 * A room type or cottage type as the public website shows it (Step 8.2a): what it is, how many it sleeps, its amenities and its
 * gallery photos (media ids; a cottage type's own photos first, then its cottages'). `units` is how many rooms of the type there are,
 * or how many cottages of the type can be booked whole.
 */
final readonly class UnitShowcase extends Data
{
    /**
     * @param  list<array{name: string, icon: string|null}>  $amenities
     * @param  list<int>  $photoIds
     */
    public function __construct(
        public UnitKind $kind,
        public int $id,
        public string $code,
        public string $name,
        public ?string $description,
        public int $maxOccupancy,
        public ?int $maxAdults,
        public ?int $maxChildren,
        public ?string $bedConfiguration,
        public ?string $sizeSqm,
        public int $bedrooms,
        public int $units,
        public array $amenities,
        public array $photoIds,
    ) {}

    public function key(): string
    {
        return $this->kind->value.':'.$this->id;
    }
}
