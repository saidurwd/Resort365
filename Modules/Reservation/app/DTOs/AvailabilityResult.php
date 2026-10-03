<?php

namespace Modules\Reservation\DTOs;

use App\Support\DTOs\Data;
use Modules\Rates\DTOs\RatePlanSummary;

/**
 * Whole cottages and room types available for a search. planViolations apply to every option
 * (e.g. the rate plan is not valid for the dates).
 */
final readonly class AvailabilityResult extends Data
{
    /**
     * @param  list<CottageOption>  $cottages
     * @param  list<RoomTypeOption>  $roomTypes
     * @param  list<RestrictionViolation>  $planViolations
     */
    public function __construct(
        public AvailabilitySearch $search,
        public RatePlanSummary $ratePlan,
        public array $cottages,
        public array $roomTypes,
        public array $planViolations,
    ) {}
}
