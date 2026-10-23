<?php

namespace Modules\Billing\Http\Controllers\Api\V1;

use App\Support\Api\ApiException;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Billing\Http\Resources\FolioResource;
use Modules\Billing\Models\Folio;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Reservation\DTOs\ReservationSummary;

/**
 * Folios, read only (Step 8.1; taking payments comes with the gateways in Step 8.3).
 *
 * @tags Billing
 */
class FolioController extends Controller
{
    /**
     * List a booking's folios.
     *
     * The guest, company and master folios of the booking with their balances.
     */
    public function forReservation(int $reservation, ReservationLookup $reservations): AnonymousResourceCollection
    {
        if (! $reservations->find($reservation) instanceof ReservationSummary) {
            throw new ApiException(404, 'not_found', __('Not found.'));
        }

        return FolioResource::collection(Folio::query()->where('reservation_id', $reservation)->orderBy('id')->get());
    }

    /**
     * Show a folio with its lines.
     */
    public function show(Folio $folio): FolioResource
    {
        return new FolioResource($folio->load(['lines' => fn ($lines) => $lines->orderBy('posting_date')->orderBy('id')]));
    }
}
