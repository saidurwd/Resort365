<?php

namespace Modules\Reservation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Reservation\Actions\CancelReservation;
use Modules\Reservation\Exceptions\ReservationNotChangeable;
use Modules\Reservation\Http\Requests\CancelReservationRequest;
use Modules\Reservation\Models\Reservation;

/**
 * Cancel a reservation: the fee under its cancellation policy is shown before confirming.
 */
class ReservationCancelController extends Controller
{
    public function create(Reservation $reservation, CancelReservation $cancel, GuestLookup $guests): View|RedirectResponse
    {
        Gate::authorize('cancel', $reservation);

        if (! $reservation->isChangeable()) {
            return to_route('reservation.bookings.show', $reservation)->with('error', __('Only tentative and confirmed bookings can be cancelled.'));
        }

        return view('reservation::bookings.cancel', [
            'reservation' => $reservation,
            'guest' => $guests->find($reservation->primary_guest_id),
            'quote' => $cancel->quote($reservation),
        ]);
    }

    public function store(CancelReservationRequest $request, Reservation $reservation, CancelReservation $cancel): RedirectResponse
    {
        try {
            $cancel->handle($reservation, (string) $request->validated('reason'), $request->user()?->getAuthIdentifier());
        } catch (ReservationNotChangeable $exception) {
            return to_route('reservation.bookings.show', $reservation)->with('error', $exception->getMessage());
        }

        return to_route('reservation.bookings.show', $reservation)->with('success', __('Booking cancelled and its rooms released.'));
    }
}
