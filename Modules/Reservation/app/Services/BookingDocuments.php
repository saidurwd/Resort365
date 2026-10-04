<?php

namespace Modules\Reservation\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Guest\DTOs\GuestSummary;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Rates\Contracts\RateLookup;
use Modules\Reservation\Models\Quote;
use Modules\Reservation\Models\QuoteItem;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationGuest;
use Modules\Reservation\Models\ReservationItem;

/**
 * The PDFs a guest gets: the confirmation voucher of a booking and a quotation (A4, dompdf).
 */
class BookingDocuments
{
    public function __construct(
        private readonly PropertyDirectory $properties,
        private readonly GuestLookup $guests,
        private readonly RateLookup $rates,
        private readonly ItemLabels $labels,
    ) {}

    public function voucher(Reservation $reservation): string
    {
        $reservation->loadMissing(['items', 'guests']);

        return Pdf::loadView('reservation::documents.voucher', [
            'reservation' => $reservation,
            'property' => $this->properties->find($reservation->property_id),
            'guest' => $this->guests->find($reservation->primary_guest_id),
            'occupants' => $reservation->guests->map(fn (ReservationGuest $row): ?GuestSummary => $this->guests->find($row->guest_id))->filter()->pluck('name')->all(),
            'items' => $reservation->items->map(fn (ReservationItem $item): array => ['label' => $this->labels->of($item), 'adults' => $item->adults, 'children' => $item->children])->all(),
            'plan' => $this->rates->ratePlan($reservation->rate_plan_id),
        ])->setPaper('a4')->output();
    }

    public function quote(Quote $quote): string
    {
        $quote->loadMissing('items');

        return Pdf::loadView('reservation::documents.quote', [
            'quote' => $quote,
            'property' => $this->properties->find($quote->property_id),
            'guest' => $this->guests->find($quote->guest_id),
            'items' => $quote->items->map(fn (QuoteItem $item): array => ['label' => $item->label, 'adults' => $item->adults, 'children' => $item->children, 'total' => $item->total])->all(),
            'plan' => $this->rates->ratePlan($quote->rate_plan_id),
        ])->setPaper('a4')->output();
    }
}
