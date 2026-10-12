<?php

namespace Modules\Inventory\Contracts;

use Modules\Inventory\DTOs\ReorderSuggestion;

/**
 * What Procurement reads to suggest purchases (Step 5.5): stocked items at or below their reorder level.
 */
interface ReorderSuggestions
{
    /**
     * The suggestions of a property's active stores, largest shortfall first within each store.
     *
     * @return list<ReorderSuggestion>
     */
    public function forProperty(int $propertyId): array;
}
