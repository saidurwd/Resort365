<?php

namespace Modules\Property\Contracts;

use Modules\Property\DTOs\CottageSummary;
use Modules\Property\DTOs\RoomSummary;
use Modules\Property\DTOs\UnitTypeSummary;
use Modules\Property\Enums\UnitKind;

/**
 * A property's room types and cottage types for other modules (Rates, Reservation), without
 * their models. Lookups respect the signed-in user's property access.
 */
interface InventoryCatalog
{
    /**
     * Room types first, then cottage types, each by sort order and name.
     *
     * @return list<UnitTypeSummary>
     */
    public function unitTypes(int $propertyId, bool $activeOnly = false): array;

    public function find(UnitKind $kind, int $id): ?UnitTypeSummary;

    /**
     * The property's rooms (active and inactive), by sort order and number.
     *
     * @return list<RoomSummary>
     */
    public function rooms(int $propertyId): array;

    /**
     * The property's cottages (active and inactive), by sort order and name.
     *
     * @return list<CottageSummary>
     */
    public function cottages(int $propertyId): array;
}
