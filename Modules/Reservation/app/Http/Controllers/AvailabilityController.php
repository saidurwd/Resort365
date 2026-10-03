<?php

namespace Modules\Reservation\Http\Controllers;

use App\Support\Tenancy\PropertyContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Rates\Contracts\RateLookup;
use Modules\Rates\DTOs\RatePlanSummary;
use Modules\Reservation\Http\Requests\SearchAvailabilityRequest;
use Modules\Reservation\Services\AvailabilityService;

/**
 * Reservations → Availability (ARCHITECTURE §6.2): dates and guests → whole cottages and rooms
 * with prices. Authorized by the reservation.availability.view route middleware; a search is
 * validated by SearchAvailabilityRequest (resolved only when the form was submitted).
 */
class AvailabilityController extends Controller
{
    public function index(Request $request, AvailabilityService $availability, RateLookup $rates, PropertyDirectory $properties): View
    {
        $propertyId = app(PropertyContext::class)->currentId() ?? abort(403, __('Choose a property first.'));
        $property = $properties->find($propertyId);
        $plans = array_values(array_filter($rates->ratePlans($propertyId), fn (RatePlanSummary $plan): bool => $plan->sellsThrough('front_desk')));
        $businessDate = CarbonImmutable::parse($property->businessDate ?? now()->toDateString());

        $result = $request->has('check_in') ? $availability->search(app(SearchAvailabilityRequest::class)->search()) : null;

        return view('reservation::availability.index', [
            'plans' => $plans,
            'values' => $request->query() + [
                'check_in' => $businessDate->toDateString(),
                'check_out' => $businessDate->addDays(2)->toDateString(),
                'adults' => 2,
                'children' => 0,
                'rate_plan' => $plans[0]->id ?? null,
            ],
            'result' => $result,
            'currency' => $property->currencyCode ?? '',
            'propertyName' => $property->name ?? '',
        ]);
    }
}
