<?php

namespace Modules\Reservation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Modules\Reservation\Actions\SetRoomCharges;
use Modules\Reservation\Http\Requests\RoomChargesRequest;
use Modules\Reservation\Models\Reservation;

class RoomChargesController extends Controller
{
    public function update(RoomChargesRequest $request, Reservation $reservation, SetRoomCharges $set): RedirectResponse
    {
        $blocked = $request->boolean('no_room_charges');
        $set->handle($reservation, $blocked, $request->user()?->getAuthIdentifier());

        return back()->with('success', $blocked ? __('Outlets may no longer charge this booking.') : __('Outlets may charge this booking again.'));
    }
}
