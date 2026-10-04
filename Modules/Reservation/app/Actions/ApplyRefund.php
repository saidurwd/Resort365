<?php

namespace Modules\Reservation\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Modules\Reservation\Enums\PaymentStatus;
use Modules\Reservation\Enums\ReservationLogAction;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Services\ReservationBalance;
use Modules\Reservation\Services\ReservationLogger;

/**
 * Records that money was paid back (Billing's RefundIssued): the booking's paid total drops. A
 * cancelled booking is Refunded once only its fee is still held, otherwise Partially refunded.
 */
class ApplyRefund extends Action
{
    public function __construct(
        private readonly ReservationBalance $balance,
        private readonly ReservationLogger $logger,
    ) {}

    public function handle(int $reservationId, string $paidTotal, string $amount): ?Reservation
    {
        return $this->transaction(function () use ($reservationId, $paidTotal, $amount): ?Reservation {
            $reservation = Reservation::query()->lockForUpdate()->find($reservationId);

            if (! $reservation instanceof Reservation) {
                return null;
            }

            $paid = (string) BigDecimal::of($paidTotal)->toScale(2);
            $cancelled = $reservation->status === ReservationStatus::Cancelled;
            $fee = $reservation->cancellation_fee ?? '0.00';

            $reservation->forceFill([
                'amount_paid' => $paid,
                'balance_due' => $this->balance->balance($cancelled ? $fee : $reservation->grand_total, $paid),
                'payment_status' => match (true) {
                    BigDecimal::of($paid)->isLessThanOrEqualTo($cancelled ? $fee : '0') => PaymentStatus::Refunded,
                    default => PaymentStatus::PartiallyRefunded,
                },
            ])->save();

            $this->logger->log($reservation, ReservationLogAction::PaymentApplied, __('Refund of :amount paid back.', ['amount' => $amount]), ['amount_paid' => [null, $paid]]);

            return $reservation;
        });
    }
}
