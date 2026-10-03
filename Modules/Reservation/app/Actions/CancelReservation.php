<?php

namespace Modules\Reservation\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Rates\Contracts\RateLookup;
use Modules\Rates\DTOs\CancellationQuote;
use Modules\Reservation\Enums\ReservationLogAction;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Events\ReservationCancelled;
use Modules\Reservation\Exceptions\ReservationNotChangeable;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationItemNight;
use Modules\Reservation\Services\ReservationBalance;
use Modules\Reservation\Services\ReservationLogger;

/**
 * Cancels a reservation (ARCHITECTURE §6.7): the cancellation policy's fee for the days before
 * arrival is kept, every room lock is released and ReservationCancelled says what is refundable.
 * An expired hold (ExpireTentativeHolds) is cancelled free of charge, and only while it is still
 * tentative and short of its deposit, so a payment that arrived just before wins.
 *
 * TODO(step-2.6): the refund itself (Billing, with approval above a threshold).
 */
class CancelReservation extends Action
{
    public function __construct(
        private readonly RateLookup $rates,
        private readonly PropertyDirectory $properties,
        private readonly ReservationBalance $balance,
        private readonly ReservationLogger $logger,
    ) {}

    /**
     * What cancelling today would cost (the cancel screen shows it before confirming).
     */
    public function quote(Reservation $reservation): CancellationQuote
    {
        $timezone = $this->properties->find($reservation->property_id)->timezone ?? 'UTC';
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $arrival = CarbonImmutable::parse($reservation->check_in->toDateString(), $timezone);

        $nightlyTotals = ReservationItemNight::query()->whereIn('reservation_item_id', $reservation->items()->select('id'))
            ->orderBy('stay_date')->get(['stay_date', 'total_amount'])
            ->groupBy(fn (ReservationItemNight $night): string => $night->stay_date->toDateString())
            ->map(fn ($nights): string => (string) $nights->reduce(fn (BigDecimal $sum, ReservationItemNight $night): BigDecimal => $sum->plus($night->total_amount), BigDecimal::zero()))
            ->values()->all();

        return $this->rates->cancellationQuote($reservation->cancellation_policy_id, $reservation->grand_total, $nightlyTotals,
            $reservation->deposit_required, $reservation->amount_paid, (int) $today->diffInDays($arrival, false));
    }

    /**
     * @param  bool  $expired  the deposit hold ran out (no fee)
     *
     * @throws ReservationNotChangeable
     */
    public function handle(Reservation $reservation, string $reason, ?int $userId = null, bool $expired = false): Reservation
    {
        return $this->transaction(function () use ($reservation, $reason, $userId, $expired): Reservation {
            $locked = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);

            if (! $locked->isChangeable()) {
                throw new ReservationNotChangeable(__('Only tentative and confirmed bookings can be cancelled.'));
            }

            if ($expired && ($locked->status !== ReservationStatus::Tentative || $this->balance->depositMet($locked->amount_paid, $locked->deposit_required))) {
                throw new ReservationNotChangeable(__('The deposit was paid in the meantime.'));
            }

            if ($expired) {
                [$fee, $refund] = ['0.00', $locked->amount_paid];
            } else {
                $quote = $this->quote($locked);
                [$fee, $refund] = [$quote->fee, $quote->refund];
            }

            $from = $locked->status;

            $locked->forceFill([
                'status' => ReservationStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'cancellation_fee' => $fee,
                'balance_due' => $this->balance->balance($fee, $locked->amount_paid),
            ])->save();
            $locked->items()->update(['status' => ReservationStatus::Cancelled->value]);
            $locked->locks()->delete();

            $this->logger->log($locked, $expired ? ReservationLogAction::Expired : ReservationLogAction::Cancelled,
                $expired ? __('Cancelled automatically: the deposit was not paid in time. Rooms released.')
                    : __('Cancelled: :reason. Fee :fee, refund due :refund. Rooms released.', ['reason' => $reason, 'fee' => $fee, 'refund' => $refund]),
                ['status' => [$from->value, ReservationStatus::Cancelled->value], 'cancellation_fee' => [null, $fee], 'refund_due' => [null, $refund]], $userId);

            ReservationCancelled::dispatch($locked->tenant_id, $locked->id, $fee, $refund, $expired);

            return $locked;
        }, attempts: 3);
    }
}
