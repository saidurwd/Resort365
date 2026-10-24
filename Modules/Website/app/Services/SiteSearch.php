<?php

namespace Modules\Website\Services;

use Carbon\CarbonImmutable;
use Modules\Rates\Contracts\RateLookup;
use Modules\Rates\DTOs\RatePlanSummary;
use Modules\Reservation\Contracts\BookingEngine;
use Modules\Reservation\DTOs\AvailabilityResult;
use Modules\Reservation\DTOs\AvailabilitySearch;
use Modules\Reservation\DTOs\Occupancy;
use Modules\Website\Models\WebsiteSite;

/**
 * The website's availability search (Step 8.2b): for each rate plan sold online, what is free for the dates and party.
 */
class SiteSearch
{
    public function __construct(private readonly BookingEngine $engine, private readonly RateLookup $rates) {}

    /**
     * @return list<AvailabilityResult> plans with at least one bookable room type or cottage first
     */
    public function search(WebsiteSite $site, CarbonImmutable $checkIn, CarbonImmutable $checkOut, int $adults, int $children, ?string $promoCode): array
    {
        $plans = array_filter($this->rates->ratePlans($site->property_id), fn (RatePlanSummary $plan): bool => $plan->sellsThrough('online'));
        $results = [];

        foreach ($plans as $plan) {
            $result = $this->engine->availability(new AvailabilitySearch($site->property_id, $plan->id, $checkIn, $checkOut, new Occupancy($adults, $children), $promoCode, 'online'));

            if ($result instanceof AvailabilityResult && $result->planViolations === []) {
                $results[] = $result;
            }
        }

        return $results;
    }
}
