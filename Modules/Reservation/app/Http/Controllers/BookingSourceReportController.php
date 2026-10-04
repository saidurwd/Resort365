<?php

namespace Modules\Reservation\Http\Controllers;

use App\Support\Tenancy\PropertyContext;
use Carbon\CarbonImmutable;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Http\Requests\BookingSourceReportRequest;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Services\BookingSourceReport;

/**
 * Booking sources of the current property for a date range, by booking date or arrival date.
 */
class BookingSourceReportController extends Controller
{
    public function index(BookingSourceReportRequest $request, PropertyContext $context, PropertyDirectory $properties, BookingSourceReport $report): View
    {
        $propertyId = $context->currentId() ?? abort(403, __('Choose a property first.'));
        $timezone = $properties->find($propertyId)->timezone ?? 'UTC';
        $today = CarbonImmutable::now($timezone);
        $from = CarbonImmutable::parse((string) $request->validated('from', $today->startOfMonth()->toDateString()), $timezone)->startOfDay();
        $to = CarbonImmutable::parse((string) $request->validated('to', $today->endOfMonth()->toDateString()), $timezone)->startOfDay();
        $by = (string) $request->validated('by', 'booked');

        $query = Reservation::query()->where('property_id', $propertyId)->withCount('items')
            ->when($by === 'arrival',
                fn ($query) => $query->whereBetween('check_in', [$from->toDateString(), $to->toDateString()]),
                fn ($query) => $query->whereBetween('created_at', [$from->utc(), $to->endOfDay()->utc()]));

        $bookings = $query->get(['id', 'source', 'status', 'check_in', 'check_out', 'grand_total'])->map(fn (Reservation $reservation): array => [
            'source' => $reservation->source,
            'cancelled' => $reservation->status === ReservationStatus::Cancelled,
            'nights' => $reservation->nights() * (int) $reservation->getAttribute('items_count'),
            'total' => $reservation->grand_total,
        ]);

        return view('reservation::reports.sources', [
            'report' => $report->summarise($bookings),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'by' => $by,
            'currency' => $properties->find($propertyId)->currencyCode ?? '',
        ]);
    }
}
