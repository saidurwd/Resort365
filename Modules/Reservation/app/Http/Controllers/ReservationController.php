<?php

namespace Modules\Reservation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Rates\Contracts\RateLookup;
use Modules\Reservation\Models\Reservation;

/**
 * A reservation's summary after booking. TODO(step-1.7): the full detail page (tabs) and the list.
 */
class ReservationController extends Controller
{
    public function show(Reservation $reservation, GuestLookup $guests, InventoryCatalog $catalog, PropertyDirectory $properties, RateLookup $rates): View
    {
        Gate::authorize('view', $reservation);
        $reservation->load('items.nights');

        $labels = [];

        foreach ($catalog->cottages($reservation->property_id) as $cottage) {
            $labels['cottage:'.$cottage->id] = $cottage->name;
        }

        foreach ($catalog->rooms($reservation->property_id) as $room) {
            $labels['room:'.$room->id] = __('Room :number', ['number' => $room->number]);
        }

        return view('reservation::bookings.show', [
            'reservation' => $reservation,
            'guest' => $guests->find($reservation->primary_guest_id),
            'labels' => $labels,
            'plan' => $rates->ratePlan($reservation->rate_plan_id),
            'timezone' => $properties->find($reservation->property_id)->timezone ?? 'UTC',
        ]);
    }
}
