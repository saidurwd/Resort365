<?php

namespace Modules\Reservation\DTOs;

use App\Support\DTOs\Data;
use Modules\Property\DTOs\CottageSummary;
use Modules\Property\DTOs\RoomSummary;

/**
 * What can be sold for a stay: whole cottages and individual rooms (AvailabilityCalculator).
 */
final readonly class AvailableInventory extends Data
{
    /**
     * @param  list<CottageSummary>  $wholeCottages
     * @param  list<RoomSummary>  $rooms
     */
    public function __construct(
        public array $wholeCottages,
        public array $rooms,
    ) {}
}
