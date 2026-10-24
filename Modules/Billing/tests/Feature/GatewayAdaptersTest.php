<?php

/*
| The payment gateway adapters (Step 8.3) against their documented APIs, with faked HTTP: SSLCommerz (session, validation API, IPN
| `verify_sign`, refund), bKash tokenized checkout (token, create, execute or query, refund) and Stripe Checkout (session, read back,
| `Stripe-Signature` webhook, refund).
*/
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Modules\Billing\DTOs\GatewayCheckout;
use Modules\Billing\DTOs\GatewaySetup;
use Modules\Billing\Enums\GatewayMode;
use Modules\Billing\Enums\GatewayOutcome;
use Modules\Billing\Enums\GatewayProvider;
use Modules\Billing\Exceptions\GatewayError;
use Modules\Billing\Services\Gateways\BkashGateway;
use Modules\Billing\Services\Gateways\SslcommerzGateway;
use Modules\Billing\Services\Gateways\StripeGateway;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // bKash keeps its token in the tenant's cache.
    app(TenantContext::class)->set(Tenant::factory()->create(['slug' => 'sunrise']));
});

function gaCheckout(): GatewayCheckout
{
    return new GatewayCheckout('7b9b1d2e-0000-4000-8000-000000000001', '4554.00', 'BDT', 'Booking RSV-1 at Sunrise', 'Nadia Karim', 'nadia@example.com', '+8801911000003',
        'https://sunrise.resort365.test/pay/return/7b9b1d2e-0000-4000-8000-000000000001', 'https://sunrise.resort365.test/pay/notify/sslcommerz/1');
}

it('starts, confirms and refunds an SSLCommerz payment', function (): void {
    $setup = new GatewaySetup(1, GatewayProvider::Sslcommerz, GatewayMode::Sandbox, ['store_id' => 'testbox', 'store_password' => 'qwerty']);
    Http::fake([
        'sandbox.sslcommerz.com/gwprocess/v4/api.php' => Http::response(['status' => 'SUCCESS', 'GatewayPageURL' => 'https://sandbox.sslcommerz.com/EasyCheckOut/abc', 'sessionkey' => 'SESS1']),
        'sandbox.sslcommerz.com/validator/api/validationserverAPI.php*' => Http::response(['status' => 'VALID', 'tran_id' => gaCheckout()->reference, 'amount' => '4554.00', 'currency' => 'BDT', 'bank_tran_id' => 'BANK123', 'store_passwd' => 'never-logged']),
        'sandbox.sslcommerz.com/validator/api/merchantTransIDvalidationAPI.php*' => Http::response(['APIConnect' => 'DONE', 'status' => 'success', 'refund_ref_id' => 'REF9']),
    ]);
    $gateway = app(SslcommerzGateway::class);

    $start = $gateway->start($setup, gaCheckout());
    expect([$start->redirectUrl, $start->gatewayRef])->toBe(['https://sandbox.sslcommerz.com/EasyCheckOut/abc', 'SESS1']);
    Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), 'gwprocess') && $request['tran_id'] === gaCheckout()->reference
        && $request['store_passwd'] === 'qwerty' && str_ends_with((string) $request['success_url'], '?outcome=success') && $request['ipn_url'] === gaCheckout()->notifyUrl);

    $paid = $gateway->confirm($setup, gaCheckout()->reference, 'SESS1', ['val_id' => 'VAL1', 'outcome' => 'success']);
    expect([$paid->outcome, $paid->amount, $paid->currency, $paid->transactionId])->toBe([GatewayOutcome::Paid, '4554.00', 'BDT', 'BANK123'])
        ->and($paid->raw)->not->toHaveKey('store_passwd');

    expect($gateway->confirm($setup, gaCheckout()->reference, 'SESS1', ['outcome' => 'cancel'])->outcome)->toBe(GatewayOutcome::Cancelled)
        ->and($gateway->confirm($setup, 'another-reference', 'SESS1', ['val_id' => 'VAL1'])->outcome)->not->toBe(GatewayOutcome::Paid);

    $refund = $gateway->refund($setup, 'SESS1', 'BANK123', '1000.00', 'BDT', 'Cancelled');
    expect([$refund->outcome, $refund->transactionId])->toBe([GatewayOutcome::Paid, 'REF9']);

});

it('reports an SSLCommerz session it refused', function (): void {
    $setup = new GatewaySetup(1, GatewayProvider::Sslcommerz, GatewayMode::Sandbox, ['store_id' => 'testbox', 'store_password' => 'wrong']);
    Http::fake(['sandbox.sslcommerz.com/gwprocess/v4/api.php' => Http::response(['status' => 'FAILED', 'failedreason' => 'Store Credential Error'])]);

    expect(fn () => app(SslcommerzGateway::class)->start($setup, gaCheckout()))->toThrow(GatewayError::class, 'Store Credential Error');
});

it('accepts an SSLCommerz IPN only with a valid verify_sign', function (): void {
    $setup = new GatewaySetup(1, GatewayProvider::Sslcommerz, GatewayMode::Sandbox, ['store_id' => 'testbox', 'store_password' => 'qwerty']);
    $fields = ['tran_id' => gaCheckout()->reference, 'val_id' => 'VAL1', 'amount' => '4554.00', 'status' => 'VALID'];
    $signed = [...$fields, 'store_passwd' => md5('qwerty')];
    ksort($signed);
    $sign = md5(implode('&', array_map(fn ($key, $value) => $key.'='.$value, array_keys($signed), $signed)));
    $gateway = app(SslcommerzGateway::class);

    $notice = $gateway->notice($setup, Request::create('/ipn', 'POST', [...$fields, 'verify_key' => 'tran_id,val_id,amount,status', 'verify_sign' => $sign]));
    expect($notice?->eventId)->toBe('ipn:VAL1')->and($notice?->reference)->toBe(gaCheckout()->reference)->and($notice?->input)->toBe(['val_id' => 'VAL1', 'outcome' => 'success']);

    expect($gateway->notice($setup, Request::create('/ipn', 'POST', [...$fields, 'amount' => '1.00', 'verify_key' => 'tran_id,val_id,amount,status', 'verify_sign' => $sign])))->toBeNull()
        ->and($gateway->notice($setup, Request::create('/ipn', 'POST', $fields)))->toBeNull();
});

it('pays and refunds through bKash tokenized checkout', function (): void {
    $setup = new GatewaySetup(2, GatewayProvider::Bkash, GatewayMode::Sandbox, ['app_key' => 'key', 'app_secret' => 'secret', 'username' => 'user', 'password' => 'pass']);
    Http::fake([
        'tokenized.sandbox.bka.sh/*/token/grant' => Http::response(['id_token' => 'TOKEN1', 'statusCode' => '0000']),
        'tokenized.sandbox.bka.sh/*/checkout/create' => Http::response(['statusCode' => '0000', 'paymentID' => 'PAY1', 'bkashURL' => 'https://sandbox.payment.bkash.com/?paymentId=PAY1']),
        'tokenized.sandbox.bka.sh/*/checkout/execute' => Http::response(['statusCode' => '2062', 'statusMessage' => 'The payment has already been completed']),
        'tokenized.sandbox.bka.sh/*/payment/status' => Http::response(['transactionStatus' => 'Completed', 'trxID' => 'TRX1', 'amount' => '4554', 'currency' => 'BDT']),
        'tokenized.sandbox.bka.sh/*/payment/refund' => Http::response(['transactionStatus' => 'Completed', 'refundTrxID' => 'RTRX1']),
    ]);
    $gateway = app(BkashGateway::class);

    $start = $gateway->start($setup, gaCheckout());
    expect([$start->redirectUrl, $start->gatewayRef])->toBe(['https://sandbox.payment.bkash.com/?paymentId=PAY1', 'PAY1']);
    Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), 'checkout/create') && $request->header('Authorization')[0] === 'TOKEN1' && $request['amount'] === '4554.00' && $request['intent'] === 'sale');

    // Executed already (a repeated callback): the payment is queried instead.
    $paid = $gateway->confirm($setup, gaCheckout()->reference, 'PAY1', ['paymentID' => 'PAY1', 'status' => 'success']);
    expect([$paid->outcome, $paid->amount, $paid->transactionId])->toBe([GatewayOutcome::Paid, '4554.00', 'TRX1']);
    expect($gateway->confirm($setup, gaCheckout()->reference, 'PAY1', ['paymentID' => 'PAY1', 'status' => 'cancel'])->outcome)->toBe(GatewayOutcome::Cancelled)
        ->and($gateway->confirm($setup, gaCheckout()->reference, 'PAY1', ['paymentID' => 'OTHER', 'status' => 'success'])->outcome)->toBe(GatewayOutcome::Failed)
        ->and($gateway->notice($setup, Request::create('/n', 'POST')))->toBeNull();

    expect($gateway->refund($setup, 'PAY1', 'TRX1', '4554.00', 'BDT', 'Cancelled')->transactionId)->toBe('RTRX1');
    // The token is granted once and kept.
    Http::assertSentCount(5);
});

it('pays through Stripe Checkout and checks the webhook signature', function (): void {
    $setup = new GatewaySetup(3, GatewayProvider::Stripe, GatewayMode::Sandbox, ['secret_key' => 'sk_test_1', 'webhook_secret' => 'whsec_1']);
    Http::fake([
        'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1']),
        'api.stripe.com/v1/checkout/sessions/cs_test_1' => Http::response(['id' => 'cs_test_1', 'client_reference_id' => gaCheckout()->reference, 'payment_status' => 'paid', 'amount_total' => 455400, 'currency' => 'bdt', 'payment_intent' => 'pi_1']),
        'api.stripe.com/v1/refunds' => Http::response(['id' => 're_1', 'status' => 'succeeded']),
    ]);
    $gateway = app(StripeGateway::class);

    expect($gateway->start($setup, gaCheckout())->gatewayRef)->toBe('cs_test_1');
    Http::assertSent(fn (HttpRequest $request): bool => str_ends_with($request->url(), '/v1/checkout/sessions') && (int) $request['line_items'][0]['price_data']['unit_amount'] === 455400
        && $request['client_reference_id'] === gaCheckout()->reference);

    $paid = $gateway->confirm($setup, gaCheckout()->reference, 'cs_test_1', ['outcome' => 'success']);
    expect([$paid->outcome, $paid->amount, $paid->currency, $paid->transactionId])->toBe([GatewayOutcome::Paid, '4554.00', 'BDT', 'pi_1']);
    expect($gateway->refund($setup, 'cs_test_1', 'pi_1', '4554.00', 'BDT', 'Cancelled')->transactionId)->toBe('re_1');

    $payload = (string) json_encode(['id' => 'evt_1', 'type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_test_1', 'client_reference_id' => gaCheckout()->reference]]]);
    $signed = fn (int $time, string $secret = 'whsec_1'): Request => tap(Request::create('/webhook', 'POST', content: $payload), fn (Request $request) => $request->headers->set('Stripe-Signature', 't='.$time.',v1='.hash_hmac('sha256', $time.'.'.$payload, $secret)));

    expect($gateway->notice($setup, $signed(time()))?->eventId)->toBe('evt_1')
        ->and($gateway->notice($setup, $signed(time()))?->input)->toBe(['session_id' => 'cs_test_1'])
        ->and($gateway->notice($setup, $signed(time(), 'whsec_wrong')))->toBeNull()
        ->and($gateway->notice($setup, $signed(time() - 3600)))->toBeNull();
});
