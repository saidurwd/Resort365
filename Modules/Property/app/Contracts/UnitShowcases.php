<?php

namespace Modules\Property\Contracts;

use Modules\Property\DTOs\UnitShowcase;

/**
 * Room types and cottage types for the public website (Step 8.2a): the active ones a guest can book, with their description,
 * capacity, amenities and photos. Cottage types are listed when at least one cottage of the type can be booked whole.
 */
interface UnitShowcases
{
    /**
     * @return list<UnitShowcase> room types first, then cottage types, each by sort order
     */
    public function forProperty(int $propertyId): array;
}
