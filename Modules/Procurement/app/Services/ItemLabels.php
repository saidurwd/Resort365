<?php

namespace Modules\Procurement\Services;

use Modules\Inventory\Contracts\ItemCatalog;
use Modules\Inventory\DTOs\ItemSummary;

/**
 * Item names and units for Procurement screens and documents, fetched in one go through Inventory's catalogue.
 */
class ItemLabels
{
    public function __construct(private readonly ItemCatalog $catalog) {}

    /**
     * @param  iterable<int>  $itemIds
     * @return array<int, ItemSummary>
     */
    public function for(iterable $itemIds): array
    {
        return $this->catalog->many(collect($itemIds)->unique()->values()->all());
    }
}
