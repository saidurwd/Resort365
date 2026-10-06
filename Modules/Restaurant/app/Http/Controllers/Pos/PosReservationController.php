<?php

namespace Modules\Restaurant\Http\Controllers\Pos;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Restaurant\Actions\CloseTableReservation;
use Modules\Restaurant\Actions\SeatTableReservation;
use Modules\Restaurant\Enums\TableReservationStatus;
use Modules\Restaurant\Exceptions\PosNotAllowed;
use Modules\Restaurant\Exceptions\RestaurantSetupInvalid;
use Modules\Restaurant\Models\TableReservation;
use Modules\Restaurant\Services\PosContext;

/**
 * Table reservations on the POS floor (ARCHITECTURE §5.10.12): the host seats a booked party (which opens
 * their order) or marks them cancelled or no-show. Booking is done in the back office.
 */
class PosReservationController extends Controller
{
    public function seat(Request $request, TableReservation $reservation, PosContext $context, SeatTableReservation $seat): RedirectResponse
    {
        abort_unless($reservation->outlet_id === $context->terminal()->outlet_id, 404);

        try {
            $order = $seat->handle($reservation, (int) $request->user()?->getAuthIdentifier(), $request->filled('table_id') ? $request->integer('table_id') : null);
        } catch (PosNotAllowed $exception) {
            return to_route('pos.floor')->with('error', $exception->getMessage());
        }

        return to_route('pos.orders.show', $order);
    }

    public function close(Request $request, TableReservation $reservation, PosContext $context, CloseTableReservation $close): RedirectResponse
    {
        abort_unless($reservation->outlet_id === $context->terminal()->outlet_id, 404);

        try {
            $close->handle($reservation, TableReservationStatus::tryFrom((string) $request->input('status')) ?? TableReservationStatus::Cancelled);
        } catch (RestaurantSetupInvalid $exception) {
            return to_route('pos.floor')->with('error', $exception->getMessage());
        }

        return to_route('pos.floor')->with('success', __('Reservation updated.'));
    }
}
