<?php

namespace Modules\Inventory\Contracts;

use Modules\Inventory\DTOs\ItemSummary;
use Modules\Inventory\Exceptions\UnitNotConvertible;

/**
 * The item catalogue for other modules (Restaurant recipes, Procurement, Housekeeping), Step 5.2.
 */
interface ItemCatalog
{
    public function find(int $itemId): ?ItemSummary;

    /**
     * Active items whose name, SKU or barcode starts with the term.
     *
     * @return list<ItemSummary>
     */
    public function search(string $term, int $limit = 20): array;

    /**
     * A quantity in some unit as a quantity in the item's base unit: the same unit, one of the item's own
     * conversions (1 box = 24 pcs), or a unit of the base unit's kind (kg to g).
     *
     * @throws UnitNotConvertible
     */
    public function toBaseQuantity(int $itemId, string $quantity, int $unitId): string;
}
