<?php

namespace Modules\Billing\Services\Gateways;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Modules\Billing\Contracts\GuestPaymentGateway;
use Modules\Billing\DTOs\GatewayCheckout;
use Modules\Billing\DTOs\GatewayNotice;
use Modules\Billing\DTOs\GatewayResult;
use Modules\Billing\DTOs\GatewaySetup;
use Modules\Billing\DTOs\GatewayStart;
use Modules\Billing\Enums\GatewayOutcome;
use Modules\Billing\Exceptions\GatewayError;

/**
 * A gateway for local and staging use (Step 8.3; never in production): its "hosted page" is our own `/pay/test/{uuid}` with Pay,
 * Fail and Cancel buttons; what comes back is signed with the application key, like a real gateway's signature, so the whole path
 * (return, notification, replay, refund) can be tried without a gateway account.
 */
class TestGateway implements GuestPaymentGateway
{
    public function start(GatewaySetup $setup, GatewayCheckout $checkout): GatewayStart
    {
        if (app()->isProduction()) {
            throw new GatewayError('The test gateway is not available in production.');
        }

        return new GatewayStart(URL::signedRoute('billing.online.test', ['uuid' => $checkout->reference], now()->addHour()), 'TEST-'.Str::upper(Str::random(10)));
    }

    public function confirm(GatewaySetup $setup, string $reference, ?string $gatewayRef, array $input): GatewayResult
    {
        $outcome = GatewayOutcome::tryFrom((string) ($input['outcome'] ?? '')) ?? GatewayOutcome::Failed;
        $amount = (string) ($input['amount'] ?? '');
        $transaction = (string) ($input['txn'] ?? '');

        if (! hash_equals(self::sign($reference, $outcome->value, $amount, $transaction), (string) ($input['sig'] ?? ''))) {
            return new GatewayResult(GatewayOutcome::Failed, message: 'Bad signature.', raw: ['outcome' => $outcome->value]);
        }

        return new GatewayResult($outcome, $amount, (string) ($input['currency'] ?? 'BDT'), $transaction !== '' ? $transaction : null, raw: ['outcome' => $outcome->value, 'amount' => $amount, 'txn' => $transaction]);
    }

    public function notice(GatewaySetup $setup, Request $request): ?GatewayNotice
    {
        $body = (string) $request->getContent();

        if (! hash_equals(hash_hmac('sha256', $body, self::key()), (string) $request->header('X-Test-Signature'))) {
            return null;
        }

        $data = json_decode($body, true);

        return is_array($data) && isset($data['event_id'], $data['reference'])
            ? new GatewayNotice((string) $data['event_id'], (string) $data['reference'], $data, $data)
            : null;
    }

    public function refund(GatewaySetup $setup, string $gatewayRef, string $transactionId, string $amount, string $currency, string $reason): GatewayResult
    {
        return new GatewayResult(GatewayOutcome::Paid, $amount, $currency, 'TEST-REFUND-'.Str::upper(Str::random(8)), raw: ['refunded' => $amount]);
    }

    /**
     * The signature of a test outcome (what the test page and notifications carry).
     */
    public static function sign(string $reference, string $outcome, string $amount, string $transaction): string
    {
        return hash_hmac('sha256', implode('|', [$reference, $outcome, $amount, $transaction]), self::key());
    }

    /**
     * The body and signature header of a test notification, for the test page and tests.
     *
     * @return array{0: string, 1: string}
     */
    public static function notification(string $eventId, string $reference, string $outcome, string $amount, string $transaction, string $currency = 'BDT'): array
    {
        $body = (string) json_encode(['event_id' => $eventId, 'reference' => $reference, 'outcome' => $outcome, 'amount' => $amount, 'txn' => $transaction,
            'currency' => $currency, 'sig' => self::sign($reference, $outcome, $amount, $transaction)]);

        return [$body, hash_hmac('sha256', $body, self::key())];
    }

    private static function key(): string
    {
        return 'test-gateway|'.config('app.key');
    }
}
