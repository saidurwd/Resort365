<?php

namespace Modules\Billing\Services;

use Brick\Math\BigDecimal;
use Illuminate\Contracts\View\View;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Enums\PaymentType;
use Modules\Billing\Enums\RefundKind;
use Modules\Billing\Models\CreditNote;
use Modules\Billing\Models\Folio;
use Modules\Billing\Models\Payment;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Reservation\DTOs\ReservationSummary;
use Modules\Reservation\Enums\ReservationStatus;

/**
 * The Payments tab of the reservation page (registered through Reservation's ReservationTabs):
 * the payments received and a form to take one. The form suggests what is still needed for the
 * deposit, or else the balance.
 */
class PaymentsTab
{
    public function __construct(
        private readonly ReservationPayments $payments,
        private readonly ReservationLookup $reservations,
        private readonly PropertyDirectory $properties,
    ) {}

    public function render(int $reservationId): View|string
    {
        $reservation = $this->reservations->find($reservationId);

        if (! $reservation instanceof ReservationSummary) {
            return '';
        }

        return view('billing::payments.tab', [
            'reservation' => $reservation,
            'payments' => Payment::query()->where('reservation_id', $reservationId)->orderBy('received_at')->orderBy('id')->get(),
            'methods' => PaymentMethod::cases(),
            'suggested' => $this->suggested($reservation),
            'timezone' => $this->properties->find($reservation->propertyId)->timezone ?? 'UTC',
            'refunds' => $this->refundOptions($reservation),
        ]);
    }

    /**
     * What is still needed for the deposit of a tentative booking, else the balance.
     */
    private function suggested(ReservationSummary $reservation): string
    {
        if ($reservation->status === ReservationStatus::Tentative) {
            $short = bcsub($reservation->depositRequired, $reservation->amountPaid, 2);

            if (bccomp($short, '0', 2) > 0) {
                return $short;
            }
        }

        return $reservation->balanceDue;
    }

    /**
     * What can be paid back now, as kind => [label, amount, source id] (amount > 0 only).
     *
     * @return list<array{kind: RefundKind, label: string, amount: string, source: int|null}>
     */
    private function refundOptions(ReservationSummary $reservation): array
    {
        $options = [];

        if (in_array($reservation->status, [ReservationStatus::Cancelled, ReservationStatus::NoShow], true)) {
            $options[] = ['kind' => RefundKind::Cancellation, 'label' => __('Cancellation refund'),
                'amount' => (string) $this->payments->paidTotal($reservation->id)->minus($reservation->cancellationFee ?? '0')->toScale(2), 'source' => null];
        }

        foreach (Payment::query()->where('reservation_id', $reservation->id)->where('payment_type', PaymentType::SecurityDeposit->value)->get() as $deposit) {
            $options[] = ['kind' => RefundKind::SecurityDeposit, 'label' => __('Return security deposit :receipt', ['receipt' => $deposit->receipt_no]),
                'amount' => (string) $this->payments->securityDepositHeld($deposit)->toScale(2), 'source' => $deposit->id];
        }

        foreach (Folio::query()->where('reservation_id', $reservation->id)->where('balance', '<', 0)->get() as $folio) {
            $options[] = ['kind' => RefundKind::Overpayment, 'label' => __('Credit balance on :no', ['no' => $folio->folio_no]),
                'amount' => (string) BigDecimal::of($folio->balance)->negated()->toScale(2), 'source' => $folio->id];
        }

        foreach (CreditNote::query()->whereHas('invoice', fn ($query) => $query->where('reservation_id', $reservation->id))->get() as $note) {
            $options[] = ['kind' => RefundKind::CreditNote, 'label' => __('Credit note :no', ['no' => $note->credit_note_no]),
                'amount' => (string) BigDecimal::of($note->refund_due)->minus($note->refunded)->toScale(2), 'source' => $note->id];
        }

        return array_values(array_filter($options, fn (array $option): bool => BigDecimal::of($option['amount'])->isPositive()));
    }
}
