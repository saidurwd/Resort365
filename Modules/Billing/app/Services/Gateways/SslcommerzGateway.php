<?php

namespace Modules\Billing\Services\Gateways;

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
 * SSLCommerz (Step 8.3): a hosted payment session; the guest comes back to the success, fail or cancel URL and SSLCommerz also sends
 * an IPN (signed with `verify_sign`). Either way the payment is confirmed with the validation API, which is the only thing trusted.
 * Refunds use the refund API with the bank transaction id.
 */
class SslcommerzGateway implements GuestPaymentGateway
{
    public function start(GatewaySetup $setup, GatewayCheckout $checkout): GatewayStart
    {
        $response = $this->call(fn () => Http::asForm()->timeout(30)->post($this->base($setup).'/gwprocess/v4/api.php', [
            'store_id' => $setup->credential('store_id'),
            'store_passwd' => $setup->credential('store_password'),
            'total_amount' => $checkout->amount,
            'currency' => $checkout->currency,
            'tran_id' => $checkout->reference,
            'success_url' => GatewayDrivers::withQuery($checkout->returnUrl, ['outcome' => 'success']),
            'fail_url' => GatewayDrivers::withQuery($checkout->returnUrl, ['outcome' => 'fail']),
            'cancel_url' => GatewayDrivers::withQuery($checkout->returnUrl, ['outcome' => 'cancel']),
            'ipn_url' => $checkout->notifyUrl,
            'cus_name' => $checkout->customerName,
            'cus_email' => $checkout->customerEmail ?: 'guest@example.com',
            'cus_phone' => $checkout->customerPhone ?: 'N/A',
            'cus_add1' => 'N/A',
            'cus_city' => 'N/A',
            'cus_country' => 'Bangladesh',
            'shipping_method' => 'NO',
            'product_name' => $checkout->description,
            'product_category' => 'Accommodation',
            'product_profile' => 'non-physical-goods',
        ]));

        $data = (array) $response->json();

        if (($data['status'] ?? '') !== 'SUCCESS' || empty($data['GatewayPageURL'])) {
            throw new GatewayError('SSLCommerz refused the session: '.($data['failedreason'] ?? $response->status()));
        }

        return new GatewayStart((string) $data['GatewayPageURL'], (string) ($data['sessionkey'] ?? ''));
    }

    public function confirm(GatewaySetup $setup, string $reference, ?string $gatewayRef, array $input): GatewayResult
    {
        $outcome = (string) ($input['outcome'] ?? '');
        $valId = (string) ($input['val_id'] ?? '');

        if ($valId === '') {
            return new GatewayResult($outcome === 'cancel' ? GatewayOutcome::Cancelled : GatewayOutcome::Failed, message: (string) ($input['error'] ?? $input['status'] ?? ''), raw: ['status' => $input['status'] ?? null]);
        }

        $data = (array) $this->call(fn () => Http::timeout(30)->get($this->base($setup).'/validator/api/validationserverAPI.php', [
            'val_id' => $valId, 'store_id' => $setup->credential('store_id'), 'store_passwd' => $setup->credential('store_password'), 'v' => 1, 'format' => 'json',
        ]))->json();
        $status = (string) ($data['status'] ?? '');

        if (! in_array($status, ['VALID', 'VALIDATED'], true) || ($data['tran_id'] ?? null) !== $reference) {
            return new GatewayResult($status === 'FAILED' ? GatewayOutcome::Failed : GatewayOutcome::Pending, message: $status, raw: $this->safe($data));
        }

        return new GatewayResult(GatewayOutcome::Paid, number_format((float) ($data['amount'] ?? 0), 2, '.', ''), (string) ($data['currency'] ?? 'BDT'),
            (string) ($data['bank_tran_id'] ?? ''), raw: $this->safe($data));
    }

    public function notice(GatewaySetup $setup, Request $request): ?GatewayNotice
    {
        $input = $request->all();
        $sign = (string) ($input['verify_sign'] ?? '');
        $keys = array_filter(explode(',', (string) ($input['verify_key'] ?? '')));

        if ($sign === '' || $keys === []) {
            return null;
        }

        $values = ['store_passwd' => md5($setup->credential('store_password'))];

        foreach ($keys as $key) {
            $values[$key] = (string) ($input[$key] ?? '');
        }

        ksort($values);
        $joined = implode('&', array_map(fn (string $key, string $value): string => $key.'='.$value, array_keys($values), $values));

        if (! hash_equals(md5($joined), $sign) || empty($input['val_id']) || empty($input['tran_id'])) {
            return null;
        }

        return new GatewayNotice('ipn:'.$input['val_id'], (string) $input['tran_id'], ['val_id' => (string) $input['val_id'], 'outcome' => 'success'], $this->safe($input));
    }

    public function refund(GatewaySetup $setup, string $gatewayRef, string $transactionId, string $amount, string $currency, string $reason): GatewayResult
    {
        $data = (array) $this->call(fn () => Http::timeout(30)->get($this->base($setup).'/validator/api/merchantTransIDvalidationAPI.php', [
            'bank_tran_id' => $transactionId, 'refund_amount' => $amount, 'refund_remarks' => mb_substr($reason, 0, 100),
            'store_id' => $setup->credential('store_id'), 'store_passwd' => $setup->credential('store_password'), 'v' => 1, 'format' => 'json',
        ]))->json();

        $done = ($data['APIConnect'] ?? '') === 'DONE' && in_array(strtolower((string) ($data['status'] ?? '')), ['success', 'processing'], true);

        return new GatewayResult($done ? GatewayOutcome::Paid : GatewayOutcome::Failed, $amount, $currency, (string) ($data['refund_ref_id'] ?? ''), (string) ($data['errorReason'] ?? ''), $this->safe($data));
    }

    private function base(GatewaySetup $setup): string
    {
        return $setup->mode === GatewayMode::Live ? 'https://securepay.sslcommerz.com' : 'https://sandbox.sslcommerz.com';
    }

    /**
     * @template T
     *
     * @param  callable(): T  $request
     * @return T
     */
    private function call(callable $request): mixed
    {
        try {
            return $request();
        } catch (ConnectionException $exception) {
            throw new GatewayError('SSLCommerz could not be reached: '.$exception->getMessage(), $exception->getCode(), previous: $exception);
        }
    }

    /**
     * The response without anything secret, for the log.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function safe(array $data): array
    {
        return array_diff_key($data, array_flip(['store_passwd', 'verify_sign', 'verify_sign_sha2', 'card_no', 'card_issuer_country_code']));
    }
}
