<?php

namespace Modules\Billing\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Modules\Billing\DTOs\GatewayResult;
use Modules\Billing\DTOs\NewPayment;
use Modules\Billing\Enums\GatewayOutcome;
use Modules\Billing\Enums\OnlinePaymentStatus;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Exceptions\PaymentNotAllowed;
use Modules\Billing\Models\OnlinePayment;

/**
 * What a gateway confirmed about an online payment (Step 8.3), done once: under a lock, an attempt that is already settled is left as
 * it is, so a repeated return or a replayed notification never pays twice. A paid result for the right amount and currency becomes a
 * Billing payment (method online, with the gateway's transaction id) through RecordPayment, which fires PaymentReceived and so
 * confirms the booking once its deposit is paid. Money the booking can no longer take (cancelled meanwhile, a different amount) is
 * kept as "paid, not applied" for staff to refund.
 */
class CompleteOnlinePayment extends Action
{
    public function __construct(private readonly RecordPayment $record) {}

    public function handle(OnlinePayment $attempt, GatewayResult $result): OnlinePayment
    {
        return $this->transaction(function () use ($attempt, $result): OnlinePayment {
            $locked = OnlinePayment::query()->lockForUpdate()->findOrFail($attempt->id);

            if ($locked->status->isFinal() && $locked->status !== OnlinePaymentStatus::Failed && $locked->status !== OnlinePaymentStatus::Cancelled) {
                return $locked;
            }

            return match ($result->outcome) {
                GatewayOutcome::Pending => $locked,
                GatewayOutcome::Failed, GatewayOutcome::Cancelled => $this->settle($locked, $result->outcome === GatewayOutcome::Failed ? OnlinePaymentStatus::Failed : OnlinePaymentStatus::Cancelled, $result->message),
                GatewayOutcome::Paid => $this->paid($locked, $result),
            };
        });
    }

    private function paid(OnlinePayment $attempt, GatewayResult $result): OnlinePayment
    {
        $attempt->transaction_id = $result->transactionId;
        $attempt->paid_at = now();

        if ($result->amount === null || ! BigDecimal::of($result->amount)->isEqualTo($attempt->amount) || strtoupper((string) $result->currency) !== $attempt->currency_code) {
            return $this->settle($attempt, OnlinePaymentStatus::Unapplied, __('The gateway took :amount :currency instead of :expected.', ['amount' => $result->amount, 'currency' => $result->currency, 'expected' => $attempt->amount]));
        }

        try {
            $payment = $this->record->handle(new NewPayment($attempt->reservation_id, PaymentMethod::Online, $attempt->amount, $result->transactionId ?: $attempt->gateway_ref,
                $attempt->provider->label(), gateway: $attempt->provider->value, gatewayTxnId: $result->transactionId));
        } catch (PaymentNotAllowed $refused) {
            return $this->settle($attempt, OnlinePaymentStatus::Unapplied, $refused->getMessage());
        }

        $attempt->payment_id = $payment->id;

        return $this->settle($attempt, OnlinePaymentStatus::Paid, null);
    }

    private function settle(OnlinePayment $attempt, OnlinePaymentStatus $status, ?string $reason): OnlinePayment
    {
        $attempt->forceFill(['status' => $status, 'failure_reason' => $reason !== null ? mb_substr($reason, 0, 500) : null])->save();

        return $attempt;
    }
}
