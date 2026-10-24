<?php

namespace Modules\Billing\Services;

use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use Modules\Billing\Enums\GatewayOutcome;
use Modules\Billing\Enums\OnlinePaymentStatus;
use Modules\Billing\Exceptions\GatewayError;
use Modules\Billing\Exceptions\PaymentNotAllowed;
use Modules\Billing\Models\OnlinePayment;
use Modules\Billing\Services\Gateways\GatewayDrivers;

/**
 * Pays a refund back through the gateways a booking was paid through online (Step 8.3): newest payment first, never more than each
 * payment still holds. Each gateway refund is logged; if a gateway refuses, nothing more is tried and the refund is not recorded.
 */
class OnlineRefunds
{
    public function __construct(private readonly GatewayDrivers $drivers, private readonly GatewayLog $log) {}

    /** How much of the booking's online payments can still be refunded online. */
    public function refundable(int $reservationId): BigDecimal
    {
        return $this->payments($reservationId)->reduce(fn (BigDecimal $sum, OnlinePayment $payment): BigDecimal => $sum->plus(BigDecimal::of($payment->amount)->minus($payment->refunded_amount)), BigDecimal::zero());
    }

    /**
     * Refunds the amount and answers the gateways' refund references (for the refund's reference).
     *
     * @throws PaymentNotAllowed
     */
    public function refund(int $reservationId, string $amount, string $reason): string
    {
        $left = BigDecimal::of($amount)->toScale(2);

        if ($left->isGreaterThan($this->refundable($reservationId))) {
            throw new PaymentNotAllowed(__('Only :amount was paid online and can go back that way.', ['amount' => (string) $this->refundable($reservationId)->toScale(2)]));
        }

        $references = [];

        foreach ($this->payments($reservationId) as $payment) {
            if (! $left->isPositive()) {
                break;
            }

            $part = BigDecimal::min($left, BigDecimal::of($payment->amount)->minus($payment->refunded_amount))->toScale(2);

            if (! $part->isPositive()) {
                continue;
            }

            try {
                $result = $this->drivers->for($payment->provider)->refund($payment->gateway->setup(), (string) $payment->gateway_ref, (string) $payment->transaction_id, (string) $part, $payment->currency_code, $reason);
            } catch (GatewayError $error) {
                $this->log->record($payment->property_id, $payment->provider, 'refund', $payment, ['amount' => (string) $part, 'error' => $error->getMessage()], outcome: 'failed');

                throw new PaymentNotAllowed(__('The gateway did not take the refund: :reason', ['reason' => $error->getMessage()]), $error->getCode(), $error);
            }

            $this->log->record($payment->property_id, $payment->provider, 'refund', $payment, ['amount' => (string) $part, ...$result->raw], outcome: $result->outcome->value);

            if ($result->outcome !== GatewayOutcome::Paid) {
                throw new PaymentNotAllowed(__('The gateway did not take the refund: :reason', ['reason' => $result->message ?: __('refused')]));
            }

            $refunded = BigDecimal::of($payment->refunded_amount)->plus($part);
            $payment->forceFill(['refunded_amount' => (string) $refunded->toScale(2), 'status' => $refunded->isGreaterThanOrEqualTo($payment->amount) ? OnlinePaymentStatus::Refunded : $payment->status])->save();
            $references[] = $payment->provider->value.':'.($result->transactionId ?? '');
            $left = $left->minus($part);
        }

        return implode(', ', $references);
    }

    /**
     * @return Collection<int, OnlinePayment>
     */
    private function payments(int $reservationId): Collection
    {
        return OnlinePayment::query()->with('gateway')->where('reservation_id', $reservationId)
            ->whereIn('status', [OnlinePaymentStatus::Paid->value, OnlinePaymentStatus::Refunded->value])->whereNotNull('transaction_id')
            ->orderByDesc('paid_at')->orderByDesc('id')->get();
    }
}
