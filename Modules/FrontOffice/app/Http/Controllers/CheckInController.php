<?php

namespace Modules\FrontOffice\Http\Controllers;

use App\Support\Tenancy\ModuleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\FrontOffice\Actions\CheckInGuest;
use Modules\FrontOffice\Http\Requests\ChangeRoomRequest;
use Modules\FrontOffice\Http\Requests\RecordIdentityRequest;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Guest\Contracts\GuestRegistry;
use Modules\Guest\DTOs\GuestIdentity;
use Modules\Guest\Enums\IdType;
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Property\DTOs\RoomSummary;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Reservation\Contracts\StayOperations;
use Modules\Reservation\DTOs\ReservationSummary;
use Modules\Reservation\Exceptions\StayNotPossible;

/**
 * The check-in screen (ARCHITECTURE §5.7): guest ID, room confirmation (or a change to another
 * room of the same type), money (balance and security deposit through Billing's payment form),
 * the check-in itself and the printable registration card.
 */
class CheckInController extends Controller
{
    public function __construct(
        private readonly ReservationLookup $reservations,
        private readonly StayOperations $stays,
        private readonly GuestLookup $guests,
    ) {}

    public function show(Request $request, int $reservation, InventoryCatalog $catalog, PropertyDirectory $properties, ModuleAccess $modules): View
    {
        Gate::authorize('frontoffice.checkin.perform');
        $summary = $this->summary($reservation);
        $roomsByType = collect($catalog->rooms($summary->propertyId))->filter(fn (RoomSummary $room): bool => $room->isActive)->groupBy('roomTypeId');

        return view('frontoffice::check-in.show', [
            'reservation' => $summary,
            'guest' => $this->guests->find($summary->primaryGuestId),
            'items' => $this->stays->roomItems($summary->id),
            'roomsByType' => $roomsByType,
            'property' => $properties->find($summary->propertyId),
            'idTypes' => IdType::options(),
            'canTakePayments' => $modules->enabled('billing') && ($request->user()?->can('billing.payment.create') ?? false),
        ]);
    }

    public function store(Request $request, int $reservation, CheckInGuest $checkIn): RedirectResponse
    {
        Gate::authorize('frontoffice.checkin.perform');
        $summary = $this->summary($reservation);

        $itemId = $request->integer('item_id') ?: null;

        try {
            $after = $checkIn->handle($summary->id, $this->userId($request), $itemId);
        } catch (StayNotPossible $exception) {
            return to_route('frontoffice.check-in.show', $summary->id)->with('error', $exception->getMessage());
        }

        // A group checking in room by room stays on this screen until every room is in.
        if ($itemId !== null && $after->itemsCheckedIn < $after->itemsTotal) {
            return to_route('frontoffice.check-in.show', $summary->id)->with('success', __(':in of :total rooms checked in.', ['in' => $after->itemsCheckedIn, 'total' => $after->itemsTotal]));
        }

        return to_route('frontoffice.desk')->with('success', __(':guest is checked in (:code).', ['guest' => $summary->groupName ?? $summary->guestName, 'code' => $summary->code]));
    }

    public function identity(RecordIdentityRequest $request, int $reservation, GuestRegistry $registry): RedirectResponse
    {
        $summary = $this->summary($reservation);
        $expiry = $request->validated('id_expiry');
        $nationality = $request->validated('nationality_code');
        $scan = $request->file('scan');

        $registry->recordIdentity(new GuestIdentity($summary->primaryGuestId, IdType::from((string) $request->validated('id_type')), (string) $request->validated('id_number'),
            is_string($expiry) ? $expiry : null, is_string($nationality) ? strtoupper($nationality) : null), $scan instanceof UploadedFile ? $scan : null,
            $this->userId($request), $request->user()?->getAttribute('name'));

        return to_route('frontoffice.check-in.show', $summary->id)->with('success', __('ID recorded.'));
    }

    public function room(ChangeRoomRequest $request, int $reservation): RedirectResponse
    {
        $summary = $this->summary($reservation);

        try {
            $this->stays->changeRoom((int) $request->validated('reservation_item_id'), (int) $request->validated('room_id'), $this->userId($request));
        } catch (StayNotPossible $exception) {
            return to_route('frontoffice.check-in.show', $summary->id)->with('error', $exception->getMessage());
        }

        return to_route('frontoffice.check-in.show', $summary->id)->with('success', __('Room changed. The price stays the same.'));
    }

    public function card(int $reservation, PropertyDirectory $properties): View
    {
        Gate::authorize('frontoffice.checkin.perform');
        $summary = $this->summary($reservation);

        return view('frontoffice::check-in.card', [
            'reservation' => $summary,
            'guest' => $this->guests->find($summary->primaryGuestId),
            'property' => $properties->find($summary->propertyId),
        ]);
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
