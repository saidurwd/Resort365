<?php

namespace Modules\Billing\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Modules\Billing\DTOs\NewPayment;
use Modules\Billing\Enums\PaymentStatus;
use Modules\Billing\Enums\PaymentType;
use Modules\Billing\Events\PaymentReceived;
use Modules\Billing\Exceptions\PaymentNotAllowed;
use Modules\Billing\Models\Payment;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Reservation\DTOs\ReservationSummary;
use Modules\Reservation\Enums\ReservationStatus;

/**
 * Records a manual payment for a reservation (ARCHITECTURE §5.9): a receipt number is taken and
 * the payment saved in one transaction, then PaymentReceived tells Reservation the new total paid.
 * Before arrival a payment is a deposit (advance). More than the balance due is refused; refunds
 * and voids come later (Step 2.6). The reservation's existing payments are read with a locking
 * read, so two payments at once cannot both pass the balance check or report a stale total.
 */
class RecordPayment extends Action
{
    public function __construct(
        private readonly ReservationLookup $reservations,
        private readonly DocumentNumbers $numbers,
    ) {}

    /**
     * @throws PaymentNotAllowed
     */
    public function handle(NewPayment $data): Payment
    {
        $reservation = $this->reservations->find($data->reservationId);

        if (! $reservation instanceof ReservationSummary) {
            throw new PaymentNotAllowed(__('Unknown reservation.'));
        }

        if (! $reservation->acceptsPayments()) {
            throw new PaymentNotAllowed(__('Payments cannot be taken for a :status booking.', ['status' => strtolower($reservation->status->label())]));
        }

        $amount = BigDecimal::of($data->amount)->toScale(2);

        if (! $amount->isPositive()) {
            throw new PaymentNotAllowed(__('The amount must be more than zero.'));
        }

        return $this->transaction(function () use ($data, $reservation, $amount): Payment {
            $paid = $this->paidSoFar($reservation->id);
            $due = BigDecimal::of($reservation->grandTotal)->minus($paid);

            if ($amount->isGreaterThan($due)) {
                throw new PaymentNotAllowed(__('The amount is more than the balance due (:due).', ['due' => (string) BigDecimal::max($due, BigDecimal::zero())->toScale(2)]));
            }

            $payment = Payment::query()->create([
                'property_id' => $reservation->propertyId,
                'receipt_no' => $this->numbers->next('payment', $reservation->propertyId),
                'reservation_id' => $reservation->id,
                'payment_type' => $reservation->status === ReservationStatus::CheckedIn ? PaymentType::Payment : PaymentType::Deposit,
                'method' => $data->method,
                'amount' => (string) $amount,
                'currency_code' => $reservation->currencyCode,
                'exchange_rate' => '1',
                'base_amount' => (string) $amount,
                'reference' => $data->reference,
                'notes' => $data->notes,
                'status' => PaymentStatus::Succeeded,
                'received_by' => $data->receivedBy,
                'received_at' => now(),
            ]);

            PaymentReceived::dispatch($payment->tenant_id, $payment->id, $payment->property_id, $payment->receipt_no, $payment->amount,
                $payment->currency_code, $reservation->id, (string) $paid->plus($amount)->toScale(2), $data->receivedBy);

            return $payment;
        }, attempts: 3);
    }

    /**
     * Succeeded payments less refunds, read with a lock so concurrent payments queue up.
     */
    private function paidSoFar(int $reservationId): BigDecimal
    {
        $payments = Payment::query()->where('reservation_id', $reservationId)->where('status', PaymentStatus::Succeeded->value)
            ->lockForUpdate()->get(['payment_type', 'amount']);

        return $payments->reduce(fn (BigDecimal $sum, Payment $payment): BigDecimal => $payment->payment_type === PaymentType::Refund
            ? $sum->minus($payment->amount) : $sum->plus($payment->amount), BigDecimal::zero());
    }
}
