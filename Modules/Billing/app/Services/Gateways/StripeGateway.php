<?php

namespace Modules\Billing\Services\Gateways;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Modules\Billing\Contracts\GuestPaymentGateway;
use Modules\Billing\DTOs\GatewayCheckout;
use Modules\Billing\DTOs\GatewayNotice;
use Modules\Billing\DTOs\GatewayResult;
use Modules\Billing\DTOs\GatewaySetup;
use Modules\Billing\DTOs\GatewayStart;
use Modules\Billing\Enums\GatewayOutcome;
use Modules\Billing\Exceptions\GatewayError;

/**
 * Stripe Checkout (Step 8.3; optional, for tenants in countries Stripe supports): a Checkout Session with the amount in the
 * currency's minor unit; the guest comes back with the session id, and the `checkout.session.completed` webhook is verified with the
 * `Stripe-Signature` header (HMAC-SHA256 of "timestamp.payload", five minutes' tolerance). Either way the session is read back from
 * Stripe to confirm it. Refunds refund the payment intent. Sandbox and live are told apart by the key (sk_test_ / sk_live_).
 */
class StripeGateway implements GuestPaymentGateway
{
    private const int TOLERANCE = 300;

    public function start(GatewaySetup $setup, GatewayCheckout $checkout): GatewayStart
    {
        $data = $this->call(fn () => $this->client($setup)->asForm()->post('https://api.stripe.com/v1/checkout/sessions', [
            'mode' => 'payment',
            'success_url' => GatewayDrivers::withQuery($checkout->returnUrl, ['outcome' => 'success']).'&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => GatewayDrivers::withQuery($checkout->returnUrl, ['outcome' => 'cancel']),
            'client_reference_id' => $checkout->reference,
            'customer_email' => $checkout->customerEmail,
            'metadata' => ['reference' => $checkout->reference],
            'line_items' => [['quantity' => 1, 'price_data' => [
                'currency' => strtolower($checkout->currency), 'unit_amount' => self::minor($checkout->amount), 'product_data' => ['name' => $checkout->description],
            ]]],
        ]));

        if (empty($data['id']) || empty($data['url'])) {
            throw new GatewayError('Stripe refused the session: '.($data['error']['message'] ?? 'unknown'));
        }

        return new GatewayStart((string) $data['url'], (string) $data['id']);
    }

    public function confirm(GatewaySetup $setup, string $reference, ?string $gatewayRef, array $input): GatewayResult
    {
        if (($input['outcome'] ?? '') === 'cancel') {
            return new GatewayResult(GatewayOutcome::Cancelled);
        }

        $session = (string) ($gatewayRef ?? $input['session_id'] ?? '');
        $data = $this->call(fn () => $this->client($setup)->get('https://api.stripe.com/v1/checkout/sessions/'.rawurlencode($session)));

        if (($data['client_reference_id'] ?? null) !== $reference) {
            return new GatewayResult(GatewayOutcome::Failed, message: 'Session does not match.');
        }

        if (($data['payment_status'] ?? '') !== 'paid') {
            return new GatewayResult(($data['status'] ?? '') === 'expired' ? GatewayOutcome::Failed : GatewayOutcome::Pending, message: (string) ($data['payment_status'] ?? ''), raw: $this->safe($data));
        }

        return new GatewayResult(GatewayOutcome::Paid, (string) BigDecimal::of((int) ($data['amount_total'] ?? 0))->dividedBy(100, 2), strtoupper((string) ($data['currency'] ?? '')),
            (string) ($data['payment_intent'] ?? ''), raw: $this->safe($data));
    }

    public function notice(GatewaySetup $setup, Request $request): ?GatewayNotice
    {
        $payload = (string) $request->getContent();
        $parts = [];

        foreach (explode(',', (string) $request->header('Stripe-Signature')) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            $parts[$key][] = $value;
        }

        $timestamp = (int) ($parts['t'][0] ?? 0);
        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $setup->credential('webhook_secret'));
        $valid = $timestamp > 0 && abs(time() - $timestamp) <= self::TOLERANCE
            && collect($parts['v1'] ?? [])->contains(fn (string $signature): bool => hash_equals($expected, $signature));

        $event = json_decode($payload, true);

        if (! $valid || ! is_array($event) || ($event['type'] ?? '') !== 'checkout.session.completed') {
            return null;
        }

        $session = (array) ($event['data']['object'] ?? []);

        return new GatewayNotice((string) ($event['id'] ?? ''), isset($session['client_reference_id']) ? (string) $session['client_reference_id'] : null,
            ['session_id' => (string) ($session['id'] ?? '')], ['id' => $event['id'] ?? null, 'type' => $event['type'], 'session' => $session['id'] ?? null]);
    }

    public function refund(GatewaySetup $setup, string $gatewayRef, string $transactionId, string $amount, string $currency, string $reason): GatewayResult
    {
        $data = $this->call(fn () => $this->client($setup)->asForm()->post('https://api.stripe.com/v1/refunds', [
            'payment_intent' => $transactionId, 'amount' => self::minor($amount), 'metadata' => ['reason' => mb_substr($reason, 0, 200)],
        ]));

        return new GatewayResult(in_array($data['status'] ?? '', ['succeeded', 'pending'], true) ? GatewayOutcome::Paid : GatewayOutcome::Failed, $amount, $currency,
            (string) ($data['id'] ?? ''), (string) ($data['error']['message'] ?? ''), $this->safe($data));
    }

    /** An amount in the currency's minor unit (cents, paisa). */
    public static function minor(string $amount): int
    {
        return BigDecimal::of($amount)->multipliedBy(100)->toScale(0, RoundingMode::HalfUp)->toInt();
    }

    private function client(GatewaySetup $setup): PendingRequest
    {
        return Http::withBasicAuth($setup->credential('secret_key'), '')->acceptJson()->timeout(30);
    }

    /**
     * @param  callable():Response  $request
     * @return array<string, mixed>
     */
    private function call(callable $request): array
    {
        try {
            return (array) $request()->json();
        } catch (ConnectionException $exception) {
            throw new GatewayError('Stripe could not be reached: '.$exception->getMessage(), $exception->getCode(), previous: $exception);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function safe(array $data): array
    {
        return array_intersect_key($data, array_flip(['id', 'object', 'status', 'payment_status', 'amount_total', 'amount', 'currency', 'payment_intent', 'client_reference_id']));
    }
}
