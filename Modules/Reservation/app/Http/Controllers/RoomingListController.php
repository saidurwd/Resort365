<?php

namespace Modules\Reservation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Reservation\Actions\SaveRoomingList;
use Modules\Reservation\Exceptions\BookingNotPossible;
use Modules\Reservation\Http\Requests\RoomingListRequest;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationGuest;
use Modules\Reservation\Models\ReservationItem;
use Modules\Reservation\Services\ItemLabels;

/**
 * A group's rooming list: who sleeps in each room or cottage.
 */
class RoomingListController extends Controller
{
    public function edit(Reservation $reservation, ItemLabels $labels, GuestLookup $guests): View
    {
        Gate::authorize('update', $reservation);
        $reservation->load(['items', 'guests']);

        return view('reservation::bookings.rooming-list', [
            'reservation' => $reservation,
            'rows' => $reservation->items->map(function (ReservationItem $item) use ($reservation, $labels, $guests): array {
                $guest = $reservation->guests->first(fn (ReservationGuest $row): bool => $row->reservation_item_id === $item->id);

                return ['item' => $item, 'label' => $labels->of($item), 'guest' => $guest instanceof ReservationGuest ? $guests->find($guest->guest_id) : null];
            }),
        ]);
    }

    public function update(RoomingListRequest $request, Reservation $reservation, SaveRoomingList $save): RedirectResponse
    {
        try {
            $count = $save->handle($reservation, (array) $request->validated('rows'), $request->user() !== null ? (int) $request->user()->getAuthIdentifier() : null);
        } catch (BookingNotPossible $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->to(route('reservation.bookings.show', $reservation).'#guests')->with('success', trans_choice(':count room named.|:count rooms named.', $count));
    }
}
