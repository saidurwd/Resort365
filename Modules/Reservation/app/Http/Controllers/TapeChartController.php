<?php

namespace Modules\Reservation\Http\Controllers;

use App\Support\Tenancy\PropertyContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Reservation\Actions\MoveOnChart;
use Modules\Reservation\Exceptions\StayNotPossible;
use Modules\Reservation\Http\Requests\MoveOnChartRequest;
use Modules\Reservation\Http\Requests\TapeChartRequest;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationItem;
use Modules\Reservation\Services\TapeChart;

/**
 * Reservations → Tape chart (ARCHITECTURE §6.8): rooms down the side, days across the top, a bar
 * per booking or block. Dragging a booking to another room posts to move(), which answers in JSON.
 */
class TapeChartController extends Controller
{
    public function index(TapeChartRequest $request, TapeChart $chart, PropertyDirectory $properties): View
    {
        Gate::authorize('viewAny', Reservation::class);
        $propertyId = app(PropertyContext::class)->currentId() ?? abort(403, __('Choose a property first.'));
        $property = $properties->find($propertyId);
        $businessDate = CarbonImmutable::parse($property->businessDate ?? now()->toDateString());
        $from = $request->from($businessDate);
        $days = $request->days();

        return view('reservation::tape-chart.index', $chart->build($propertyId, $from, $days) + [
            'from' => $from,
            'windowDays' => $days,
            'windows' => TapeChartRequest::WINDOWS,
            'businessDate' => $businessDate,
            'propertyName' => $property->name ?? '',
            'canMove' => $request->user()?->can('reservation.booking.update') ?? false,
            'canBook' => $request->user()?->can('reservation.booking.create') ?? false,
        ]);
    }

    public function move(MoveOnChartRequest $request, MoveOnChart $move): JsonResponse
    {
        $item = ReservationItem::query()->findOrFail((int) $request->validated('item_id'));
        Gate::authorize('update', Reservation::query()->findOrFail($item->reservation_id));

        try {
            $message = $move->handle($item, (int) $request->validated('room_id'), (int) $request->user()?->getAuthIdentifier());
        } catch (StayNotPossible $exception) {
            return response()->json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }

        // The chart reloads after a move; the flash message tells what happened.
        session()->flash('success', $message);

        return response()->json(['ok' => true, 'message' => $message]);
    }
}
