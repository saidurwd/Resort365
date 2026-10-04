<?php

namespace Modules\FrontOffice\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\FrontOffice\Http\Requests\StayChangeRequest;
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Property\DTOs\CottageSummary;
use Modules\Property\DTOs\RoomSummary;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Reservation\Contracts\StayOperations;
use Modules\Reservation\DTOs\ReservationSummary;
use Modules\Reservation\Exceptions\StayNotPossible;

/**
 * Changes to a stay in house (ARCHITECTURE §6.7): move a room, extend the stay, leave early.
 */
class StayChangeController extends Controller
{
    public function __construct(
        private readonly ReservationLookup $reservations,
        private readonly StayOperations $stays,
    ) {}

    public function show(int $reservation, InventoryCatalog $catalog, PropertyDirectory $properties): View
    {
        Gate::authorize('frontoffice.stay.change');
        $summary = $this->summary($reservation);
        $cottages = collect($catalog->cottages($summary->propertyId))->mapWithKeys(fn (CottageSummary $cottage): array => [$cottage->id => $cottage->name]);

        return view('frontoffice::stay.show', [
            'reservation' => $summary,
            'property' => $properties->find($summary->propertyId),
            'items' => $this->stays->roomItems($summary->id),
            'rooms' => collect($catalog->rooms($summary->propertyId))->filter(fn (RoomSummary $room): bool => $room->isActive)
                ->mapWithKeys(fn (RoomSummary $room): array => [$room->id => __('Room :number', ['number' => $room->number]).' · '.$cottages->get($room->cottageId, '')])->all(),
        ]);
    }

    public function move(StayChangeRequest $request, int $reservation): RedirectResponse
    {
        $summary = $this->summary($reservation);
        $reprice = $request->boolean('reprice');

        if ($reprice) {
            Gate::authorize('frontoffice.stay.reprice');
        }

        return $this->attempt($summary, fn () => $this->stays->moveRoom((int) $request->validated('reservation_item_id'), (int) $request->validated('room_id'), $reprice, $this->userId($request)),
            __('Room moved. The old room is free from tonight.'));
    }

    public function extend(StayChangeRequest $request, int $reservation): RedirectResponse
    {
        $summary = $this->summary($reservation);

        return $this->attempt($summary, fn (): ReservationSummary => $this->stays->extendStay($summary->id, (string) $request->validated('check_out'), $this->userId($request)), __('Stay extended.'));
    }

    public function shorten(StayChangeRequest $request, int $reservation): RedirectResponse
    {
        $summary = $this->summary($reservation);

        return $this->attempt($summary, fn (): ReservationSummary => $this->stays->shortenStay($summary->id, (string) $request->validated('check_out'), $this->userId($request)),
            __('Departure moved earlier. Check out when the guest leaves.'));
    }

    private function attempt(ReservationSummary $summary, callable $change, string $success): RedirectResponse
    {
        try {
            $change();
        } catch (StayNotPossible $exception) {
            return to_route('frontoffice.stay.show', $summary->id)->with('error', $exception->getMessage());
        }

        return to_route('frontoffice.stay.show', $summary->id)->with('success', $success);
    }

    private function summary(int $reservationId): ReservationSummary
    {
        return $this->reservations->find($reservationId) ?? abort(404);
    }

    private function userId(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_numeric($id) ? (int) $id : null;
    }
}
