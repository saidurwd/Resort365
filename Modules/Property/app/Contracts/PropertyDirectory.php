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

    /**
     * The tenant's active properties the user may see (all of them outside a user request, e.g.
     * the scheduler), by name.
     *
     * @return list<PropertySummary>
     */
    public function all(): array;
}
