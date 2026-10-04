<?php

namespace Modules\Billing\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Modules\Billing\DTOs\NewRefund;
use Modules\Billing\Enums\FolioLineType;
use Modules\Billing\Enums\PaymentStatus;
use Modules\Billing\Enums\PaymentType;
use Modules\Billing\Enums\RefundKind;
use Modules\Billing\Events\RefundIssued;
use Modules\Billing\Exceptions\PaymentNotAllowed;
use Modules\Billing\Models\CreditNote;
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
 * Pays money back, with a reason (ARCHITECTURE §5.9; billing.refund.issue, the approval engine
 * arrives in Step 5.1). How much may go back depends on why:
 * - cancellation: what a cancelled booking paid above its cancellation fee;
 * - security deposit: what is still held of that deposit;
 * - overpayment: a folio's credit balance;
 * - credit note: its refund still due.
 * A refund is a payment of type refund; refunds of booking money show on the guest folio and lower
 * what the booking has paid (RefundIssued tells Reservation).
 */
class RefundPayment extends Action
{
    public function __construct(
        private readonly ReservationLookup $reservations,
        private readonly ReservationPayments $payments,
        private readonly FolioLedger $ledger,
        private readonly DocumentNumbers $numbers,
    ) {}

    /**
     * @throws PaymentNotAllowed
     */
    public function handle(NewRefund $data): Payment
    {
        $reservation = $this->reservations->find($data->reservationId) ?? throw new PaymentNotAllowed(__('Unknown reservation.'));
        $amount = BigDecimal::of($data->amount)->toScale(2);

        if (! $amount->isPositive()) {
            throw new PaymentNotAllowed(__('The amount must be more than zero.'));
        }

        return $this->transaction(function () use ($data, $reservation, $amount): Payment {
            [$available, $folio, $source] = $this->refundable($data, $reservation);

            if ($amount->isGreaterThan($available)) {
                throw new PaymentNotAllowed(__('At most :amount can be refunded.', ['amount' => (string) BigDecimal::max($available, BigDecimal::zero())->toScale(2)]));
            }

            $refund = Payment::query()->create([
                'property_id' => $reservation->propertyId,
                'receipt_no' => $this->numbers->next('payment', $reservation->propertyId),
                'reservation_id' => $reservation->id,
                'folio_id' => $folio?->id,
                'payment_type' => PaymentType::Refund,
                'method' => $data->method,
                'amount' => (string) $amount,
                'currency_code' => $reservation->currencyCode,
                'exchange_rate' => '1',
                'base_amount' => (string) $amount,
                'reference' => $data->reference,
                'reason' => $data->reason,
                'refund_kind' => $data->kind,
                'refunded_payment_id' => $data->kind === RefundKind::SecurityDeposit ? $source?->getKey() : null,
                'credit_note_id' => $data->kind === RefundKind::CreditNote ? $source?->getKey() : null,
                'status' => PaymentStatus::Succeeded,
                'received_by' => $data->issuedBy,
                'received_at' => now(),
            ]);

            if ($folio instanceof Folio) {
                FolioLine::query()->create([
                    'property_id' => $folio->property_id, 'folio_id' => $folio->id, 'posting_date' => $this->ledger->businessDate($folio->property_id),
                    'line_type' => FolioLineType::Refund, 'description' => __('Refund :receipt: :reason', ['receipt' => $refund->receipt_no, 'reason' => $data->reason]),
                    'quantity' => '1', 'unit_price' => $refund->amount, 'amount' => $refund->amount, 'tax_amount' => '0', 'total' => $refund->amount,
                    'reference_type' => 'payment', 'reference_id' => $refund->id, 'posted_by' => $data->issuedBy,
                ]);
                $this->ledger->recalculate($folio);
            }

            if ($source instanceof CreditNote) {
                $source->forceFill(['refunded' => (string) BigDecimal::of($source->refunded)->plus($amount)->toScale(2)])->save();
            }

            $paidTotal = $data->kind === RefundKind::SecurityDeposit ? null : (string) $this->payments->paidTotal($reservation->id)->toScale(2);
            RefundIssued::dispatch($refund->tenant_id, $refund->id, $refund->property_id, $refund->amount, $data->kind, $reservation->id, $paidTotal);

            return $refund;
        }, attempts: 3);
    }

    /**
     * How much may be refunded, the folio the refund shows on (if any) and its source record.
     *
     * @return array{0: BigDecimal, 1: Folio|null, 2: Payment|CreditNote|null}
     *
     * @throws PaymentNotAllowed
     */
    private function refundable(NewRefund $data, ReservationSummary $reservation): array
    {
        return match ($data->kind) {
            RefundKind::Cancellation => $this->cancellation($reservation),
            RefundKind::SecurityDeposit => $this->securityDeposit($data, $reservation),
            RefundKind::Overpayment => $this->overpayment($data, $reservation),
            RefundKind::CreditNote => $this->creditNote($data, $reservation),
        };
    }

    /**
     * @return array{0: BigDecimal, 1: Folio, 2: null}
     */
    private function cancellation(ReservationSummary $reservation): array
    {
        if ($reservation->status !== ReservationStatus::Cancelled) {
            throw new PaymentNotAllowed(__('Only a cancelled booking gets a cancellation refund.'));
        }

        return [$this->payments->paidTotal($reservation->id, lock: true)->minus($reservation->cancellationFee ?? '0'), $this->ledger->guestFolio($reservation->id), null];
    }

    /**
     * @return array{0: BigDecimal, 1: null, 2: Payment}
     */
    private function securityDeposit(NewRefund $data, ReservationSummary $reservation): array
    {
        $deposit = Payment::query()->where('reservation_id', $reservation->id)->where('payment_type', PaymentType::SecurityDeposit->value)->lockForUpdate()->find($data->sourceId)
            ?? throw new PaymentNotAllowed(__('Choose the security deposit to return.'));

        return [$this->payments->securityDepositHeld($deposit), null, $deposit];
    }

    /**
     * @return array{0: BigDecimal, 1: Folio, 2: null}
     */
    private function overpayment(NewRefund $data, ReservationSummary $reservation): array
    {
        $folio = Folio::query()->where('reservation_id', $reservation->id)->lockForUpdate()->find($data->sourceId) ?? $this->ledger->guestFolio($reservation->id);

        return [BigDecimal::of($folio->balance)->negated(), $folio, null];
    }

    /**
     * @return array{0: BigDecimal, 1: null, 2: CreditNote}
     */
    private function creditNote(NewRefund $data, ReservationSummary $reservation): array
    {
        $note = CreditNote::query()->whereHas('invoice', fn ($query) => $query->where('reservation_id', $reservation->id))->lockForUpdate()->find($data->sourceId)
            ?? throw new PaymentNotAllowed(__('Choose the credit note to refund.'));

        return [BigDecimal::of($note->refund_due)->minus($note->refunded), null, $note];
    }
}
