<?php

namespace Modules\Reservation\Actions;

use App\Support\Actions\Action;
use Carbon\CarbonImmutable;
use Modules\Reservation\DTOs\NewReservation;
use Modules\Reservation\Enums\QuoteStatus;
use Modules\Reservation\Exceptions\BookingNotPossible;
use Modules\Reservation\Exceptions\QuoteNotOpen;
use Modules\Reservation\Exceptions\RoomNoLongerAvailable;
use Modules\Reservation\Models\Quote;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Services\QuoteSnapshot;

/**
 * Books a quote at its quoted prices (ARCHITECTURE §5.6): CreateReservation saves the frozen
 * prices instead of today's, and locks the rooms as for any booking, so a room taken since the
 * quote was made fails cleanly (RoomNoLongerAvailable) and the quote stays open. The quote is
 * locked while it converts, so it can be booked only once.
 */
class ConvertQuote extends Action
{
    public function __construct(
        private readonly CreateReservation $create,
        private readonly QuoteSnapshot $snapshot,
    ) {}

    /**
     * @throws BookingNotPossible|QuoteNotOpen|RoomNoLongerAvailable
     */
    public function handle(Quote $quote, ?int $userId = null): Reservation
    {
        return $this->transaction(function () use ($quote, $userId): Reservation {
            $locked = Quote::query()->with('items.nights')->lockForUpdate()->findOrFail($quote->id);

            if ($locked->currentStatus() !== QuoteStatus::Draft && $locked->currentStatus() !== QuoteStatus::Sent) {
                throw new QuoteNotOpen(__('Quote :code is :status and cannot be booked.', ['code' => $locked->code, 'status' => strtolower($locked->currentStatus()->label())]));
            }

            $reservation = $this->create->handle(new NewReservation(
                $locked->property_id, CarbonImmutable::parse($locked->check_in->toDateString()), CarbonImmutable::parse($locked->check_out->toDateString()),
                $this->snapshot->bookingItems($locked), $locked->guest_id, $locked->source, $locked->company_id, $locked->travel_agent_id,
                $locked->deposit_percent, $locked->promo_code, $locked->special_requests,
                trim(__('From quote :code.', ['code' => $locked->code]).' '.$locked->internal_notes), $userId, allowDepositOverride: true,
            ), $this->snapshot->bookingQuote($locked));

            $locked->forceFill(['status' => QuoteStatus::Accepted, 'reservation_id' => $reservation->id, 'accepted_at' => now()])->save();

            return $reservation;
        }, attempts: 3);
    }
}
