<?php

namespace Modules\Billing\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Modules\Billing\DTOs\NewPayment;
use Modules\Billing\Enums\FolioLineType;
use Modules\Billing\Enums\PaymentStatus;
use Modules\Billing\Enums\PaymentType;
use Modules\Billing\Events\PaymentReceived;
use Modules\Billing\Exceptions\PaymentNotAllowed;
use Modules\Billing\Models\Folio;
use Modules\Billing\Models\FolioLine;
use Modules\Billing\Models\Payment;
use Modules\Billing\Services\FolioLedger;
use Modules\Billing\Services\ReservationPayments;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Reservation\DTOs\ReservationSummary;
use Modules\Reservation\Enums\ReservationStatus;

/**
 * Records a manual payment for a reservation (ARCHITECTURE §5.9): a receipt number is taken, the
 * payment saved and posted to a folio in one transaction, then PaymentReceived tells Reservation
 * what the booking has paid. Before arrival a payment is a deposit, capped by the booking's balance
 * and posted to the guest folio. In house, it goes to the folio chosen (the guest's by default) and
 * is capped by that folio's balance, which also holds extras; the booking's paid total reported to
 * Reservation never exceeds the stay's grand total (the folio is the bill from check-in on).
 * A security deposit is held apart (see securityDeposit()).
 */
class RecordPayment extends Action
{
    public function __construct(
        private readonly ReservationLookup $reservations,
        private readonly DocumentNumbers $numbers,
        private readonly FolioLedger $ledger,
        private readonly ReservationPayments $payments,
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
            if ($data->securityDeposit) {
                return $this->securityDeposit($data, $reservation, $amount);
            }

            $paid = $this->payments->paidTotal($reservation->id, lock: true);
            $inHouse = $reservation->status === ReservationStatus::CheckedIn || $data->folioId !== null;
            $folio = $data->folioId !== null
                ? Folio::query()->where('reservation_id', $reservation->id)->lockForUpdate()->find($data->folioId) ?? throw new PaymentNotAllowed(__('That folio does not belong to this booking.'))
                : $this->ledger->guestFolio($reservation->id);
            $due = $inHouse ? BigDecimal::of($folio->balance) : BigDecimal::of($reservation->grandTotal)->minus($paid);

            if ($amount->isGreaterThan($due)) {
                throw new PaymentNotAllowed(__('The amount is more than the balance due (:due).', ['due' => (string) BigDecimal::max($due, BigDecimal::zero())->toScale(2)]));
            }

            $payment = Payment::query()->create([
                'property_id' => $reservation->propertyId,
                'receipt_no' => $this->numbers->next('payment', $reservation->propertyId),
                'reservation_id' => $reservation->id,
                'folio_id' => $folio->id,
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

            FolioLine::query()->create([
                'property_id' => $folio->property_id, 'folio_id' => $folio->id, 'posting_date' => $this->ledger->businessDate($folio->property_id),
                'line_type' => FolioLineType::Payment, 'description' => __(':method payment :receipt', ['method' => $payment->method->label(), 'receipt' => $payment->receipt_no]),
                'quantity' => '1', 'unit_price' => $payment->amount, 'amount' => $payment->amount, 'tax_amount' => '0', 'total' => $payment->amount,
                'reference_type' => 'payment', 'reference_id' => $payment->id, 'posted_by' => $data->receivedBy,
            ]);
            $this->ledger->recalculate($folio);

            PaymentReceived::dispatch($payment->tenant_id, $payment->id, $payment->property_id, $payment->receipt_no, $payment->amount,
                $payment->currency_code, $reservation->id, (string) BigDecimal::min($paid->plus($amount), BigDecimal::of($reservation->grandTotal))->toScale(2), $data->receivedBy);

            return $payment;
        }, attempts: 3);
    }

    /**
     * A refundable security deposit: no cap, not on a folio, not part of what the booking has paid;
     * RefundPayment gives it back at check-out.
     */
    private function securityDeposit(NewPayment $data, ReservationSummary $reservation, BigDecimal $amount): Payment
    {
        $payment = Payment::query()->create([
            'property_id' => $reservation->propertyId,
            'receipt_no' => $this->numbers->next('payment', $reservation->propertyId),
            'reservation_id' => $reservation->id,
            'payment_type' => PaymentType::SecurityDeposit,
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
            $payment->currency_code, $reservation->id, null, $data->receivedBy);

        return $payment;
    }
}
