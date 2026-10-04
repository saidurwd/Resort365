<?php

namespace Modules\FrontOffice\Http\Controllers;

use App\Support\Tenancy\PropertyContext;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Guest\DTOs\GuestSummary;
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Property\DTOs\RoomSummary;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Reservation\DTOs\ReservationSummary;

/**
 * The front-desk dashboard (ARCHITECTURE §5.7, §10.3) of the current property on its business
 * date: arrivals, departures, in-house guests, VIPs, pending deposits and tonight's occupancy.
 */
class FrontDeskController extends Controller
{
    public function index(PropertyContext $context, PropertyDirectory $properties, ReservationLookup $reservations, GuestLookup $guests, InventoryCatalog $catalog): View
    {
        Gate::authorize('frontoffice.desk.view');

        $propertyId = $context->currentId() ?? abort(403, __('Choose a property first.'));
        $property = $properties->find($propertyId) ?? abort(404);
        $date = $property->businessDate;

        $arrivals = $reservations->arrivals($propertyId, $date);
        $departures = $reservations->departures($propertyId, $date);
        $inHouse = $reservations->inHouse($propertyId);
        $rooms = count(array_filter($catalog->rooms($propertyId), fn (RoomSummary $room): bool => $room->isActive));
        $booked = $reservations->roomsBooked($propertyId, $date);

        $vipIds = [];

        foreach ([...$arrivals, ...$inHouse] as $reservation) {
            $guest = $guests->find($reservation->primaryGuestId);

            if ($guest instanceof GuestSummary && $guest->vipLevel !== 'none') {
                $vipIds[$reservation->id] = $guest->vipLevel;
            }
        }

        return view('frontoffice::desk.index', [
            'property' => $property,
            'arrivals' => $arrivals,
            'departures' => $departures,
            'inHouse' => $inHouse,
            'pending' => $reservations->pendingDeposits($propertyId),
            'vips' => array_values(array_filter([...$arrivals, ...$inHouse], fn (ReservationSummary $reservation): bool => isset($vipIds[$reservation->id]))),
            'vipLevels' => $vipIds,
            'occupancy' => [
                'rooms' => $rooms,
                'booked' => $booked,
                'free' => max(0, $rooms - $booked),
                'percent' => $rooms > 0 ? (int) round($booked * 100 / $rooms) : 0,
            ],
        ]);
    }
}
