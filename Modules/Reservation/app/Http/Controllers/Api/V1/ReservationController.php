<?php

namespace Modules\Reservation\Http\Controllers\Api\V1;

use App\Support\Api\ApiAbility;
use App\Support\Api\ApiException;
use App\Support\Tenancy\PropertyContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Guest\Contracts\GuestRegistry;
use Modules\Guest\DTOs\GuestDetails;
use Modules\Reservation\Actions\CancelReservation;
use Modules\Reservation\Actions\CreateReservation;
use Modules\Reservation\Http\Requests\Api\CancelReservationRequest;
use Modules\Reservation\Http\Requests\Api\CreateReservationRequest;
use Modules\Reservation\Http\Requests\Api\ReservationIndexRequest;
use Modules\Reservation\Http\Resources\ReservationResource;
use Modules\Reservation\Models\Reservation;

/**
 * Bookings (Step 8.1).
 *
 * @tags Reservation
 */
class ReservationController extends Controller
{
    /**
     * List bookings.
     *
     * The bookings of the properties the token may use, newest first, with filters. `updated_since` suits a sync.
     */
    public function index(ReservationIndexRequest $request, PropertyContext $properties): AnonymousResourceCollection
    {
        if ($request->filled('property_id') && ! $properties->canAccess($request->integer('property_id'))) {
            throw new ApiException(403, 'property_denied', __('This token may not work in property :id.', ['id' => $request->integer('property_id')]));
        }

        $reservations = Reservation::query()
            ->with(['items', 'guests'])
            ->when($request->filled('property_id'), fn ($query) => $query->where('property_id', $request->integer('property_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', (string) $request->input('status')))
            ->when($request->filled('source'), fn ($query) => $query->where('source', (string) $request->input('source')))
            ->when($request->filled('guest_id'), fn ($query) => $query->whereHas('guests', fn ($guests) => $guests->where('guest_id', $request->integer('guest_id'))))
            ->when($request->filled('arrival_from'), fn ($query) => $query->where('check_in', '>=', (string) $request->input('arrival_from')))
            ->when($request->filled('arrival_to'), fn ($query) => $query->where('check_in', '<=', (string) $request->input('arrival_to')))
            ->when($request->filled('updated_since'), fn ($query) => $query->where('updated_at', '>=', CarbonImmutable::parse((string) $request->input('updated_since'))->utc()))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return ReservationResource::collection($reservations);
    }

    /**
     * Show a booking.
     */
    public function show(Reservation $reservation): ReservationResource
    {
        return new ReservationResource($reservation->load(['items', 'guests']));
    }

    /**
     * Create a booking.
     *
     * Books the rooms and cottages at today's prices, for an existing guest or a new one. The booking is `tentative` until its
     * deposit is paid (`confirmed` when none is due). Send an `Idempotency-Key` header so that a retry never books twice. A room
     * taken meanwhile is a 409 `room_no_longer_available`.
     */
    public function store(CreateReservationRequest $request, CreateReservation $create, GuestRegistry $registry, GuestLookup $guests): JsonResponse
    {
        $details = $request->newGuest();

        if ($details instanceof GuestDetails && ! ApiAbility::allows($request->user(), 'guest.guest.create')) {
            throw new AuthorizationException(__('Registering a new guest needs the guest.guest.create ability; send guest_id instead.'));
        }

        $guestId = $details instanceof GuestDetails ? $registry->register($details)->id : $request->integer('guest_id');

        if ($guests->isBlacklisted($guestId)) {
            throw new ApiException(422, 'guest_blacklisted', __('This guest is blacklisted; the front desk must decide on this booking.'));
        }

        $reservation = $create->handle($request->reservation($guestId, (int) $request->user()?->getAuthIdentifier()));

        return new ReservationResource($reservation->load(['items', 'guests']))->response()->setStatusCode(201);
    }

    /**
     * Cancel a booking.
     *
     * Cancels it and frees its rooms; the fee follows its cancellation policy and is shown in `cancellation`.
     */
    public function cancel(CancelReservationRequest $request, Reservation $reservation, CancelReservation $cancel): ReservationResource
    {
        $cancelled = $cancel->handle($reservation, (string) $request->validated('reason'), (int) $request->user()?->getAuthIdentifier());

        return new ReservationResource($cancelled->load(['items', 'guests']));
    }
}
