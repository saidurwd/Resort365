<?php

namespace Modules\Reservation\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Modules\Rates\Contracts\RateLookup;
use Modules\Reservation\Enums\ReservationLogAction;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Events\ReservationNoShow;
use Modules\Reservation\Exceptions\StayNotPossible;
use Modules\Reservation\Models\InventoryLock;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationItemNight;
use Modules\Reservation\Services\ReservationBalance;
use Modules\Reservation\Services\ReservationLogger;

/**
 * Marks a booking that did not arrive as a no-show (ARCHITECTURE §6.7, night audit step 3): status
 * No-show, the cancellation policy's no-show charge is kept as the fee (paid above it is refundable,
 * as for a cancellation), and the rooms are released from the night after the audited date; the
 * arrival night stays booked.
 */
class MarkNoShow extends Action
{
    public function __construct(
        private readonly RateLookup $rates,
        private readonly ReservationBalance $balance,
        private readonly ReservationLogger $logger,
    ) {}

    /**
     * @param  string  $date  the business date being audited (Y-m-d)
     *
     * @throws StayNotPossible
     */
    public function handle(int $reservationId, string $date, ?int $userId = null): Reservation
    {
        return $this->transaction(function () use ($reservationId, $date, $userId): Reservation {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservationId);

            if (! in_array($reservation->status, [ReservationStatus::Tentative, ReservationStatus::Confirmed], true) || $reservation->check_in->toDateString() > $date) {
                throw new StayNotPossible(__('Booking :code is not an arrival still expected on :date.', ['code' => $reservation->code, 'date' => $date]));
            }

            $nightlyTotals = ReservationItemNight::query()->whereIn('reservation_item_id', $reservation->items()->select('id'))
                ->orderBy('stay_date')->get(['stay_date', 'total_amount'])
                ->groupBy(fn (ReservationItemNight $night): string => $night->stay_date->toDateString())
                ->map(fn ($nights): string => (string) $nights->reduce(fn (BigDecimal $sum, ReservationItemNight $night): BigDecimal => $sum->plus($night->total_amount), BigDecimal::zero()))
                ->values()->all();
            $quote = $this->rates->cancellationQuote($reservation->cancellation_policy_id, $reservation->grand_total, $nightlyTotals,
                $reservation->deposit_required, $reservation->amount_paid, null);
            $from = $reservation->status;

            $reservation->forceFill([
                'status' => ReservationStatus::NoShow,
                'cancelled_at' => now(),
                'cancellation_reason' => __('No-show'),
                'cancellation_fee' => $quote->fee,
                'balance_due' => $this->balance->balance($quote->fee, $reservation->amount_paid),
            ])->save();
            $reservation->items()->update(['status' => ReservationStatus::NoShow->value]);
            InventoryLock::query()->where('reservation_id', $reservation->id)->where('stay_date', '>', $date)->delete();

            $this->logger->log($reservation, ReservationLogAction::NoShow,
                __('No-show (night audit of :date). Fee :fee, refund due :refund. Rooms released from the next night.', [
                    'date' => $date, 'fee' => $quote->fee, 'refund' => $quote->refund,
                ]),
                ['status' => [$from->value, ReservationStatus::NoShow->value], 'cancellation_fee' => [null, $quote->fee], 'refund_due' => [null, $quote->refund]], $userId);

            ReservationNoShow::dispatch($reservation->tenant_id, $reservation->id, $quote->fee, $quote->refund);

            return $reservation;
        }, attempts: 3);
    }
}
