<?php

namespace Modules\Reservation\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Modules\Reservation\Enums\ReservationLogAction;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Services\DepositConfirmation;
use Modules\Reservation\Services\ReservationBalance;
use Modules\Reservation\Services\ReservationLogger;

/**
 * Records what a reservation has been paid in total (Billing's PaymentReceived, ARCHITECTURE §4.5)
 * and auto-confirms a tentative booking once its deposit is covered (§6.5 rule 3). Billing sends
 * the total, not just this payment, so handling the same event twice changes nothing.
 */
class ApplyPayment extends Action
{
    public function __construct(
        private readonly ReservationBalance $balance,
        private readonly DepositConfirmation $confirmation,
        private readonly ReservationLogger $logger,
    ) {}

    /**
     * @param  string  $paidTotal  everything paid for the reservation so far
     * @param  string  $amount  this payment
     */
    public function handle(int $reservationId, string $paidTotal, string $amount, ?string $receiptNo = null, ?int $userId = null): ?Reservation
    {
        return $this->transaction(function () use ($reservationId, $paidTotal, $amount, $receiptNo, $userId): ?Reservation {
            $reservation = Reservation::query()->lockForUpdate()->find($reservationId);

            if (! $reservation instanceof Reservation) {
                return null;
            }

            $paid = (string) BigDecimal::of($paidTotal)->toScale(2);
            $owed = $reservation->status === ReservationStatus::Cancelled ? ($reservation->cancellation_fee ?? '0.00') : $reservation->grand_total;
            $before = $reservation->amount_paid;

            $reservation->forceFill([
                'amount_paid' => $paid,
                'balance_due' => $this->balance->balance($owed, $paid),
                'payment_status' => $this->balance->status($reservation->grand_total, $paid, $reservation->deposit_required),
            ])->save();

            $this->logger->log($reservation, ReservationLogAction::PaymentApplied, $receiptNo !== null
                ? __('Payment of :amount received (receipt :receipt).', ['amount' => $amount, 'receipt' => $receiptNo])
                : __('Payment of :amount received.', ['amount' => $amount]), ['amount_paid' => [$before, $paid]], $userId);

            if ($reservation->isChangeable()) {
                $this->confirmation->confirmIfMet($reservation, $userId);
            }

            return $reservation;
        }, attempts: 3);
    }
}
