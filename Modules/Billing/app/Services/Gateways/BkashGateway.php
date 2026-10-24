<?php

namespace Modules\Billing\Services\Gateways;

use App\Support\Tenancy\TenantCache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Modules\Billing\Contracts\GuestPaymentGateway;
use Modules\Billing\DTOs\GatewayCheckout;
use Modules\Billing\DTOs\GatewayNotice;
use Modules\Billing\DTOs\GatewayResult;
use Modules\Billing\DTOs\GatewaySetup;
use Modules\Billing\DTOs\GatewayStart;
use Modules\Billing\Enums\GatewayMode;
use Modules\Billing\Enums\GatewayOutcome;
use Modules\Billing\Exceptions\GatewayError;

/**
 * bKash tokenized checkout (Step 8.3): grant a token (kept 50 minutes), create the payment and send the guest to bKash; bKash calls
 * back with `paymentID` and `status`, and the payment is executed (or, if that was done already, queried) to confirm it. bKash has no
 * signed notifications in tokenized checkout, so confirmation comes only from asking it. Refunds use the refund API.
 */
class BkashGateway implements GuestPaymentGateway
{
    public function __construct(private readonly TenantCache $cache) {}

    public function start(GatewaySetup $setup, GatewayCheckout $checkout): GatewayStart
    {
        $data = $this->post($setup, '/tokenized/checkout/create', [
            'mode' => '0011',
            'payerReference' => $checkout->customerPhone ?: substr($checkout->reference, 0, 20),
            'callbackURL' => $checkout->returnUrl,
            'amount' => $checkout->amount,
            'currency' => 'BDT',
            'intent' => 'sale',
            'merchantInvoiceNumber' => substr(str_replace('-', '', $checkout->reference), 0, 30),
        ]);

        if (($data['statusCode'] ?? '') !== '0000' || empty($data['bkashURL']) || empty($data['paymentID'])) {
            throw new GatewayError('bKash refused the payment: '.($data['statusMessage'] ?? $data['errorMessage'] ?? 'unknown'));
        }

        return new GatewayStart((string) $data['bkashURL'], (string) $data['paymentID']);
    }

    public function confirm(GatewaySetup $setup, string $reference, ?string $gatewayRef, array $input): GatewayResult
    {
        $paymentId = (string) ($input['paymentID'] ?? $gatewayRef ?? '');
        $status = strtolower((string) ($input['status'] ?? 'success'));

        if ($paymentId === '' || $paymentId !== $gatewayRef) {
            return new GatewayResult(GatewayOutcome::Failed, message: 'Unknown bKash payment.');
        }

        if ($status !== 'success') {
            return new GatewayResult($status === 'cancel' ? GatewayOutcome::Cancelled : GatewayOutcome::Failed, message: $status);
        }

        $data = $this->post($setup, '/tokenized/checkout/execute', ['paymentID' => $paymentId]);

        if (($data['transactionStatus'] ?? '') !== 'Completed') {
            // Executed before (a repeated callback) or not finished: ask for its state.
            $data = $this->post($setup, '/tokenized/checkout/payment/status', ['paymentID' => $paymentId]);
        }

        if (($data['transactionStatus'] ?? '') !== 'Completed') {
            return new GatewayResult(in_array($data['transactionStatus'] ?? '', ['Initiated', 'Pending Authorized'], true) ? GatewayOutcome::Pending : GatewayOutcome::Failed,
                message: (string) ($data['statusMessage'] ?? $data['transactionStatus'] ?? ''), raw: $data);
        }

        return new GatewayResult(GatewayOutcome::Paid, number_format((float) ($data['amount'] ?? 0), 2, '.', ''), (string) ($data['currency'] ?? 'BDT'), (string) ($data['trxID'] ?? ''), raw: $data);
    }

    public function notice(GatewaySetup $setup, Request $request): ?GatewayNotice
    {
        return null;
    }

    public function refund(GatewaySetup $setup, string $gatewayRef, string $transactionId, string $amount, string $currency, string $reason): GatewayResult
    {
        $data = $this->post($setup, '/tokenized/checkout/payment/refund', [
            'paymentID' => $gatewayRef, 'amount' => $amount, 'trxID' => $transactionId, 'sku' => 'booking', 'reason' => mb_substr($reason, 0, 100),
        ]);

        return new GatewayResult(($data['transactionStatus'] ?? '') === 'Completed' ? GatewayOutcome::Paid : GatewayOutcome::Failed, $amount, $currency,
            (string) ($data['refundTrxID'] ?? ''), (string) ($data['statusMessage'] ?? $data['errorMessage'] ?? ''), $data);
    }

    /**
     * @param  array<string, string>  $body
     * @return array<string, mixed>
     */
    private function post(GatewaySetup $setup, string $path, array $body): array
    {
        try {
            $response = Http::withHeaders(['Authorization' => $this->token($setup), 'X-APP-Key' => $setup->credential('app_key')])->acceptJson()->timeout(30)
                ->post($this->base($setup).$path, $body);
        } catch (ConnectionException $exception) {
            throw new GatewayError('bKash could not be reached: '.$exception->getMessage(), $exception->getCode(), previous: $exception);
        }

        return (array) $response->json();
    }

    private function token(GatewaySetup $setup): string
    {
        return (string) $this->cache->remember('bkash:token:'.$setup->gatewayId, 3000, function () use ($setup): string {
            try {
                $response = Http::withHeaders(['username' => $setup->credential('username'), 'password' => $setup->credential('password')])->acceptJson()->timeout(30)
                    ->post($this->base($setup).'/tokenized/checkout/token/grant', ['app_key' => $setup->credential('app_key'), 'app_secret' => $setup->credential('app_secret')]);
            } catch (ConnectionException $exception) {
                throw new GatewayError('bKash could not be reached: '.$exception->getMessage(), $exception->getCode(), previous: $exception);
            }

            return (string) ($response->json('id_token') ?? throw new GatewayError('bKash refused the credentials: '.$response->json('statusMessage', $response->status())));
        });
    }

    private function base(GatewaySetup $setup): string
    {
        return $setup->mode === GatewayMode::Live ? 'https://tokenized.pay.bka.sh/v1.2.0-beta' : 'https://tokenized.sandbox.bka.sh/v1.2.0-beta';
    }
}
