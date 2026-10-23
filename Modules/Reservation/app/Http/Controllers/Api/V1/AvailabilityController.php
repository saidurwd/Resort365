<?php

namespace Modules\Reservation\Http\Controllers\Api\V1;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Rates\Contracts\RateLookup;
use Modules\Rates\DTOs\RatePlanSummary;
use Modules\Reservation\DTOs\AvailabilityResult;
use Modules\Reservation\Http\Requests\Api\AvailabilityRequest;
use Modules\Reservation\Http\Resources\AvailabilityResource;
use Modules\Reservation\Services\AvailabilityService;

/**
 * Availability (Step 8.1).
 *
 * @tags Reservation
 */
class AvailabilityController extends Controller
{
    /**
     * Search availability.
     *
     * What can be sold for the dates and party: per rate plan, the room types with their free rooms and the cottages free whole,
     * each with its price night by night. Without `rate_plan_id` every plan sold at the front desk is searched.
     */
    public function __invoke(AvailabilityRequest $request, AvailabilityService $availability, RateLookup $rates): AnonymousResourceCollection
    {
        $plans = $request->filled('rate_plan_id')
            ? [$request->integer('rate_plan_id')]
            : array_map(fn (RatePlanSummary $plan): int => $plan->id, array_values(array_filter($rates->ratePlans($request->integer('property_id')), fn (RatePlanSummary $plan): bool => $plan->sellsThrough('front_desk'))));

        $results = array_values(array_filter(array_map(fn (int $planId): ?AvailabilityResult => $availability->search($request->searchFor($planId)), $plans)));

        return AvailabilityResource::collection($results)->additional(['meta' => [
            'property_id' => $request->integer('property_id'),
            'check_in' => (string) $request->input('check_in'),
            'check_out' => (string) $request->input('check_out'),
            'adults' => $request->integer('adults'),
            'children' => $request->integer('children'),
        ]]);
    }
}
