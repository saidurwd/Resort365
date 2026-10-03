<?php

namespace Modules\Reservation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Reservation\Actions\AddReservationGuest;
use Modules\Reservation\Actions\MakePrimaryGuest;
use Modules\Reservation\Actions\RemoveReservationGuest;
use Modules\Reservation\Exceptions\BookingNotPossible;
use Modules\Reservation\Exceptions\ReservationNotChangeable;
use Modules\Reservation\Http\Requests\AddReservationGuestRequest;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationGuest;

/**
 * The Guests tab: add a guest, remove one, make one primary.
 */
class ReservationGuestController extends Controller
{
    public function store(AddReservationGuestRequest $request, Reservation $reservation, AddReservationGuest $add): RedirectResponse
    {
        $itemId = $request->validated('reservation_item_id');

        try {
            $add->handle($reservation, (int) $request->validated('guest_id'), is_numeric($itemId) ? (int) $itemId : null);
        } catch (BookingNotPossible|ReservationNotChangeable $exception) {
            return $this->toGuests($reservation)->withErrors(['guest_id' => $exception->getMessage()]);
        }

        return $this->toGuests($reservation)->with('success', __('Guest added.'));
    }

    public function destroy(Reservation $reservation, ReservationGuest $guest, RemoveReservationGuest $remove): RedirectResponse
    {
        Gate::authorize('update', $reservation);

        try {
            $remove->handle($reservation, $guest);
        } catch (BookingNotPossible|ReservationNotChangeable $exception) {
            return $this->toGuests($reservation)->with('error', $exception->getMessage());
        }

        return $this->toGuests($reservation)->with('success', __('Guest removed.'));
    }

    public function primary(Reservation $reservation, ReservationGuest $guest, MakePrimaryGuest $make): RedirectResponse
    {
        Gate::authorize('update', $reservation);

        try {
            $make->handle($reservation, $guest);
        } catch (ReservationNotChangeable $exception) {
            return $this->toGuests($reservation)->with('error', $exception->getMessage());
        }

        return $this->toGuests($reservation)->with('success', __('Primary guest changed.'));
    }

    private function toGuests(Reservation $reservation): RedirectResponse
    {
        return redirect()->to(route('reservation.bookings.show', $reservation).'#guests');
    }
}
