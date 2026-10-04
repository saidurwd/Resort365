<?php

namespace Modules\Billing\Services;

use Brick\Math\BigDecimal;
use Modules\Billing\Enums\PaymentStatus;
use Modules\Billing\Enums\PaymentType;
use Modules\Billing\Enums\RefundKind;
use Modules\Billing\Models\Payment;

/**
 * What a reservation has paid: payments less refunds. Security deposits (and their return) are
 * held apart and never count. With lock, the reservation's payments are read with a locking read,
 * so concurrent payments and refunds queue up.
 */
class ReservationPayments
{
    public function paidTotal(int $reservationId, bool $lock = false): BigDecimal
    {
        $query = Payment::query()->where('reservation_id', $reservationId)->where('status', PaymentStatus::Succeeded->value);
        $payments = ($lock ? $query->lockForUpdate() : $query)->get(['payment_type', 'refund_kind', 'amount']);

        return $payments->reduce(fn (BigDecimal $sum, Payment $payment): BigDecimal => match (true) {
            $payment->payment_type === PaymentType::SecurityDeposit, $payment->refund_kind === RefundKind::SecurityDeposit => $sum,
            $payment->payment_type === PaymentType::Refund => $sum->minus($payment->amount),
            default => $sum->plus($payment->amount),
        }, BigDecimal::zero());
    }

    /**
     * What remains to give back of a security deposit payment.
     */
    public function securityDepositHeld(Payment $deposit): BigDecimal
    {
        $returned = Payment::query()->where('refunded_payment_id', $deposit->id)->where('status', PaymentStatus::Succeeded->value)->sum('amount');

        return BigDecimal::of($deposit->amount)->minus((string) $returned);
    }
}
