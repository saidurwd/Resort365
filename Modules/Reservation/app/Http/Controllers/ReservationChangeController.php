<?php

namespace Modules\Reservation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Property\DTOs\CottageSummary;
use Modules\Property\DTOs\RoomSummary;
use Modules\Property\Enums\BookingMode;
use Modules\Rates\Contracts\RateLookup;
use Modules\Reservation\Actions\ModifyReservation;
use Modules\Reservation\Exceptions\BookingNotPossible;
use Modules\Reservation\Exceptions\ReservationNotChangeable;
use Modules\Reservation\Exceptions\RoomNoLongerAvailable;
use Modules\Reservation\Http\Requests\ModifyReservationRequest;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationItem;

/**
 * Change a reservation's stay: edit (dates and items) → review (old and new price) → save.
 */
class ReservationChangeController extends Controller
{
    public function edit(Reservation $reservation, InventoryCatalog $catalog, RateLookup $rates): View|RedirectResponse
    {
        Gate::authorize('update', $reservation);

        if (! $reservation->isChangeable()) {
            return to_route('reservation.bookings.show', $reservation)->with('error', __('Only tentative and confirmed bookings can be changed.'));
        }

        $reservation->load('items');
        $rows = old('items', [
            ...$reservation->items->map(fn (ReservationItem $item): array => [
                'unit' => $item->room_id !== null ? 'room:'.$item->room_id : 'cottage:'.$item->cottage_id,
                'rate_plan' => $item->rate_plan_id, 'adults' => $item->adults, 'children' => $item->children,
            ])->all(),
            ['unit' => '', 'rate_plan' => $reservation->rate_plan_id, 'adults' => 2, 'children' => 0],
        ]);

        return view('reservation::bookings.edit', [
            'reservation' => $reservation,
            'rows' => is_array($rows) ? array_values($rows) : [],
            'units' => $this->units($catalog, $reservation->property_id),
            'plans' => collect($rates->ratePlans($reservation->property_id))->pluck('name', 'id')->all(),
        ]);
    }

    public function review(ModifyReservationRequest $request, Reservation $reservation, ModifyReservation $modify): View|RedirectResponse
    {
        try {
            $quote = $modify->quote($reservation, $request->change());
        } catch (BookingNotPossible $exception) {
            return back()->withInput()->withErrors(['items' => $exception->getMessage()]);
        }

        return view('reservation::bookings.review', [
            'reservation' => $reservation,
            'change' => $request->change(),
            'quote' => $quote,
            'input' => $request->only(['check_in', 'check_out', 'items']),
        ]);
    }

    public function update(ModifyReservationRequest $request, Reservation $reservation, ModifyReservation $modify): RedirectResponse
    {
        try {
            $modify->handle($reservation, $request->change(), $request->user()?->getAuthIdentifier());
        } catch (RoomNoLongerAvailable|BookingNotPossible|ReservationNotChangeable $exception) {
            return to_route('reservation.bookings.edit', $reservation)->withInput()->withErrors(['items' => $exception->getMessage()]);
        }

        return to_route('reservation.bookings.show', $reservation)->with('success', __('Stay changed. Rooms re-locked and the price recalculated.'));
    }

    /**
     * Bookable units for the item picker: whole cottages (where allowed), then active rooms.
     *
     * @return array<string, string> unit key => label
     */
    private function units(InventoryCatalog $catalog, int $propertyId): array
    {
        $cottages = collect($catalog->cottages($propertyId))->filter(fn (CottageSummary $cottage): bool => $cottage->isActive && $cottage->bookingMode !== BookingMode::RoomsOnly);
        $names = collect($catalog->cottages($propertyId))->pluck('name', 'id');
        $rooms = collect($catalog->rooms($propertyId))->filter(fn (RoomSummary $room): bool => $room->isActive);

        return [
            ...$cottages->mapWithKeys(fn (CottageSummary $cottage): array => ['cottage:'.$cottage->id => __(':name (whole cottage)', ['name' => $cottage->name])])->all(),
            ...$rooms->mapWithKeys(fn (RoomSummary $room): array => ['room:'.$room->id => __('Room :number', ['number' => $room->number]).' · '.$names->get($room->cottageId, '')])->all(),
        ];
    }
}
