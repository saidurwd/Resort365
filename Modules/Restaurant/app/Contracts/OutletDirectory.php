<?php

namespace Modules\Restaurant\Contracts;

use Modules\Restaurant\DTOs\OutletListing;

/**
 * A property's active outlets for other modules (the public website's Dining page, Step 8.2a).
 */
interface OutletDirectory
{
    /**
     * @return list<OutletListing> by sort order
     */
    public function forProperty(int $propertyId): array;
}
