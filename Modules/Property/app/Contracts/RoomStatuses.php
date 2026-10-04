<?php

namespace Modules\Property\Contracts;

use Modules\Property\Enums\HousekeepingStatus;

/**
 * Rooms' cleaning status (rooms.housekeeping_status), which Housekeeping keeps up to date
 * (ARCHITECTURE §5.11).
 */
interface RoomStatuses
{
    /**
     * Every room of the property (active or not) with its cleaning status.
     *
     * @return array<int, HousekeepingStatus> room id => status
     */
    public function forProperty(int $propertyId): array;

    /**
     * Sets the cleaning status of rooms of the current tenant.
     *
     * @param  list<int>  $roomIds
     * @return array<int, HousekeepingStatus> room id => the status before, for the rooms that changed
     */
    public function set(array $roomIds, HousekeepingStatus $status): array;
}
