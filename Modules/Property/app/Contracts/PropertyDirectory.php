<?php

namespace Modules\Property\Contracts;

use Modules\Property\DTOs\PropertySummary;

/**
 * Property details for other modules (Rates, Reservation, Front Office…). Lookups respect the
 * signed-in user's property access.
 */
interface PropertyDirectory
{
    public function find(int $id): ?PropertySummary;

    /**
     * The property the user is working in (navbar switcher), if any.
     */
    public function current(): ?PropertySummary;
}
