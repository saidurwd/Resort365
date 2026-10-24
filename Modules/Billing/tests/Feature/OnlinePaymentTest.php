<?php

/*
| Paying bookings online (Step 8.3) with the test gateway: starting a payment, the guest coming back, notifications and their replays,
| money the booking can no longer take, refunds through the gateway, the gateway setup screen and payment links. Setup
| (booking-setup.php): Sunrise CXB, room 401 for three nights (bookStay's default) at 22,770.00 with a 30% deposit (6,831.00).
*/
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Modules\Billing\Actions\RefundPayment;
use Modules\Billing\Actions\StartOnlinePayment;
use Modules\Billing\DTOs\NewRefund;
use Modules\Billing\DTOs\OnlinePaymentRequest;
use Modules\Billing\Enums\GatewayProvider;
use Modules\Billing\Enums\OnlinePaymentPurpose;
use Modules\Billing\Enums\OnlinePaymentStatus;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Enums\RefundKind;
use Modules\Billing\Exceptions\PaymentNotAllowed;
use Modules\Billing\Models\GatewayEvent;
use Modules\Billing\Models\OnlinePayment;
use Modules\Billing\Models\Payment;
use Modules\Billing\Models\PaymentGateway;
use Modules\Billing\Notifications\PaymentLinkMessage;
use Modules\Billing\Services\Gateways\TestGateway;
use Modules\Reservation\Actions\CancelReservation;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Models\Reservation;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\call;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

require_once __DIR__.'/../../../Reservation/tests/Support/management-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    booking(fn () => PaymentGateway::factory()->provider(GatewayProvider::Test)->create(['property_id' => bookingIds()['property']]));
    Notification::fake();
});

/**
 * Starts a test-gateway payment of the booking; answers the attempt and where the guest goes.
 *
 * @return array{0: OnlinePayment, 1: string}
 */
function opStart(Reservation $reservation, ?string $amount = null): array
{
    return booking(fn (): array => StartOnlinePayment::make()->handle(new OnlinePaymentRequest($reservation->id, GatewayProvider::Test, $amount ?? $reservation->deposit_required, OnlinePaymentPurpose::Deposit, 'https://site.example/booking')));
}

/**
 * The guest comes back from the test gateway with the outcome.
 *
 * @return TestResponse<Response>
 */
function opReturn(OnlinePayment $attempt, string $outcome = 'paid', ?string $amount = null): TestResponse
{
    $amount ??= $attempt->amount;
    $txn = $outcome === 'paid' ? 'TEST-TXN-1' : '';

    return get(tenantUrl('sunrise', '/pay/return/'.$attempt->uuid.'?'.http_build_query(['outcome' => $outcome, 'amount' => $amount, 'currency' => 'BDT', 'txn' => $txn, 'sig' => TestGateway::sign($attempt->uuid, $outcome, $amount, $txn)])));
}

/** @return TestResponse<Response> */
function opNotify(OnlinePayment $attempt, string $eventId, string $outcome = 'paid'): TestResponse
{
    [$body, $signature] = TestGateway::notification($eventId, $attempt->uuid, $outcome, $attempt->amount, 'TEST-TXN-1');

    return call('POST', tenantUrl('sunrise', '/pay/notify/test/'.$attempt->payment_gateway_id), [], [], [], ['HTTP_X_TEST_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'], $body);
}

it('takes a deposit online and confirms the booking', function (): void {
    $reservation = bookStay(['401']);
    [$attempt, $url] = opStart($reservation);

    expect($attempt->status)->toBe(OnlinePaymentStatus::Pending)->and($url)->toContain('/pay/test/'.$attempt->uuid.'?expires=');
    get($url)->assertOk()->assertSeeHtml('data-test-pay');

    opReturn($attempt)->assertRedirect('https://site.example/booking?payment=paid');

    $payment = booking(fn (): Payment => Payment::query()->sole());
    expect([$payment->method, $payment->amount, $payment->gateway, $payment->gateway_txn_id])->toBe([PaymentMethod::Online, '6831.00', 'test', 'TEST-TXN-1'])
        ->and(freshReservation($reservation->id)->status)->toBe(ReservationStatus::Confirmed)
        ->and(booking(fn (): ?OnlinePaymentStatus => $attempt->fresh()?->status))->toBe(OnlinePaymentStatus::Paid)
        ->and(booking(fn (): int => GatewayEvent::query()->where('online_payment_id', $attempt->id)->count()))->toBe(2);
});

it('never pays twice for a repeated return or a replayed notification', function (): void {
    $reservation = bookStay(['401']);
    [$attempt] = opStart($reservation);

    opNotify($attempt, 'evt-1')->assertOk()->assertSee('OK');
    opNotify($attempt, 'evt-1')->assertOk()->assertSee('Already processed.');
    opReturn($attempt)->assertRedirect('https://site.example/booking?payment=paid');
    opReturn($attempt)->assertRedirect('https://site.example/booking?payment=paid');
    opNotify($attempt, 'evt-2')->assertOk();

    expect(booking(fn (): int => Payment::query()->count()))->toBe(1)
        ->and(freshReservation($reservation->id)->amount_paid)->toBe('6831.00');
});

it('refuses notifications with a bad signature and returns that were tampered with', function (): void {
    [$attempt] = opStart(bookStay(['401']));

    call('POST', tenantUrl('sunrise', '/pay/notify/test/'.$attempt->payment_gateway_id), [], [], [], ['HTTP_X_TEST_SIGNATURE' => 'forged', 'CONTENT_TYPE' => 'application/json'], '{"event_id":"x","reference":"'.$attempt->uuid.'"}')
        ->assertStatus(400);
    get(tenantUrl('sunrise', '/pay/return/'.$attempt->uuid.'?outcome=paid&amount=6831.00&txn=X&sig=forged'))->assertRedirect('https://site.example/booking?payment=failed');

    expect(booking(fn (): int => Payment::query()->count()))->toBe(0)
        ->and(booking(fn (): bool => GatewayEvent::query()->where('signature_valid', false)->exists()))->toBeTrue();
});

it('keeps money the booking can no longer take for staff to refund', function (): void {
    $reservation = bookStay(['401']);
    [$attempt] = opStart($reservation);
    booking(fn () => CancelReservation::make()->handle(Reservation::query()->findOrFail($reservation->id), 'Guest changed plans'));

    opReturn($attempt)->assertRedirect('https://site.example/booking?payment=review');
    expect(booking(fn (): ?OnlinePaymentStatus => $attempt->fresh()?->status))->toBe(OnlinePaymentStatus::Unapplied);

    [$other] = opStart(bookStay(['402']));
    opReturn($other, 'paid', '100.00');
    expect(booking(fn (): ?OnlinePaymentStatus => $other->fresh()?->status))->toBe(OnlinePaymentStatus::Unapplied)
        ->and(booking(fn (): int => Payment::query()->count()))->toBe(0);
});

it('starts payments only through switched-on gateways and for what is owed', function (): void {
    $reservation = bookStay(['401']);

    expect(fn () => opStart($reservation, '99999.00'))->toThrow(PaymentNotAllowed::class)
        ->and(fn () => opStart($reservation, '0'))->toThrow(PaymentNotAllowed::class);

    booking(fn () => PaymentGateway::query()->update(['is_enabled' => false]));
    expect(fn () => opStart($reservation))->toThrow(PaymentNotAllowed::class, 'not available');

    opReturn(booking(fn (): OnlinePayment => OnlinePayment::factory()->create(['provider' => GatewayProvider::Test, 'reservation_id' => $reservation->id, 'payment_gateway_id' => PaymentGateway::query()->value('id')])), 'cancelled')
        ->assertRedirect();
});

it('refunds back through the gateway', function (): void {
    $reservation = bookStay(['401']);
    [$attempt] = opStart($reservation);
    opReturn($attempt);
    booking(fn () => CancelReservation::make()->handle(Reservation::query()->findOrFail($reservation->id), 'Guest changed plans'));

    $refund = booking(fn (): Payment => RefundPayment::make()->handle(new NewRefund($reservation->id, RefundKind::Cancellation, PaymentMethod::Online, '6831.00', 'Free cancellation')));

    expect([$refund->method, $refund->amount])->toBe([PaymentMethod::Online, '6831.00'])->and($refund->reference)->toStartWith('test:TEST-REFUND-')
        ->and(booking(fn (): array => [$attempt->fresh()?->status, $attempt->fresh()?->refunded_amount]))->toBe([OnlinePaymentStatus::Refunded, '6831.00'])
        ->and(fn () => booking(fn () => RefundPayment::make()->handle(new NewRefund($reservation->id, RefundKind::Cancellation, PaymentMethod::Online, '1.00', 'Again'))))->toThrow(PaymentNotAllowed::class);
});

it('sets up gateways with credentials kept secret', function (): void {
    staffUser(DefaultRole::GeneralManager);

    get(tenantUrl('sunrise', '/billing/online-payments'))->assertOk()->assertSeeHtml('data-gateway="sslcommerz"')->assertSeeHtml('data-gateway="test"');
    put(tenantUrl('sunrise', '/billing/online-payments/sslcommerz'), ['mode' => 'sandbox', 'is_enabled' => '1', 'credentials' => ['store_id' => 'testbox', 'store_password' => '']])
        ->assertSessionHasErrors('credentials');
    put(tenantUrl('sunrise', '/billing/online-payments/sslcommerz'), ['mode' => 'sandbox', 'is_enabled' => '1', 'credentials' => ['store_id' => 'testbox', 'store_password' => 'qwerty']])
        ->assertRedirect(tenantUrl('sunrise', '/billing/online-payments'));
    put(tenantUrl('sunrise', '/billing/online-payments/sslcommerz'), ['mode' => 'live', 'is_enabled' => '1', 'credentials' => ['store_id' => '', 'store_password' => '']]);

    $gateway = booking(fn (): PaymentGateway => PaymentGateway::query()->where('provider', 'sslcommerz')->sole());
    expect($gateway->credentials)->toBe(['store_id' => 'testbox', 'store_password' => 'qwerty'])->and($gateway->mode->value)->toBe('live')
        ->and((string) DB::table('payment_gateways')->where('id', $gateway->id)->value('credentials'))->not->toContain('qwerty');
    get(tenantUrl('sunrise', '/billing/online-payments'))->assertDontSee('qwerty');

    staffUser(DefaultRole::FrontDeskAgent);
    get(tenantUrl('sunrise', '/billing/online-payments'))->assertForbidden();
});

it('emails a payment link that pays what is still owed', function (): void {
    staffUser(DefaultRole::FrontOfficeManager);
    $reservation = bookStay(['401']);

    post(tenantUrl('sunrise', '/billing/reservations/'.$reservation->id.'/payment-links'), ['purpose' => 'deposit'])->assertRedirect()->assertSessionHas('payment_link');
    $link = (string) session('payment_link');
    Notification::assertSentOnDemand(PaymentLinkMessage::class, fn (PaymentLinkMessage $message): bool => $message->link === $link && str_contains($message->body, '6,831.00'));

    get($link)->assertOk()->assertSeeHtml('data-amount="6831.00"')->assertSeeHtml('data-pay-with="test"');
    post($link, ['provider' => 'test'])->assertRedirect();
    $attempt = booking(fn (): OnlinePayment => OnlinePayment::query()->sole());
    expect([$attempt->amount, $attempt->purpose, $attempt->return_url])->toBe(['6831.00', OnlinePaymentPurpose::Deposit, $link]);

    opReturn($attempt)->assertRedirect($link.'&payment=paid');
    get($link.'&payment=paid')->assertOk()->assertSeeHtml('data-paid')->assertSeeHtml('data-nothing-due');

    get(tenantUrl('sunrise', '/pay/link/'.$reservation->id.'/deposit'))->assertForbidden();
    $expired = booking(fn (): string => URL::temporarySignedRoute('billing.pay-link.show', now()->subMinute(), ['reservation' => $reservation->id, 'purpose' => 'balance']));
    get($expired)->assertForbidden();
});

it('lists the gateway hosts in the security policy', function (): void {
    expect((string) get(tenantUrl('sunrise', '/login'))->headers->get('Content-Security-Policy'))
        ->toContain('https://sandbox.sslcommerz.com')->toContain('https://securepay.sslcommerz.com')->toContain('https://checkout.stripe.com')->toContain('https://payment.bkash.com');
});
