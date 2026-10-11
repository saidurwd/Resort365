<?php

namespace Modules\Inventory\Contracts;

use Modules\Inventory\DTOs\StoreSummary;

/**
 * The stores of the tenant's properties for other modules (Restaurant outlets, later Procurement), Step 5.2.
 */
interface StoreDirectory
{
    public function find(int $storeId): ?StoreSummary;

    /**
     * @return list<StoreSummary>
     */
    public function forProperty(int $propertyId, bool $activeOnly = true): array;
}
