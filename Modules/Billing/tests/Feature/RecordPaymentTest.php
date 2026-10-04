<?php

/*
| Payments (ARCHITECTURE §5.9). Step 1.7 "Done when": taking a 30% deposit confirms the booking
| automatically (Billing's PaymentReceived → Reservation's ApplyPayment).
|
| Room 401 for three nights: 22,770.00, deposit 30% = 6,831.00 (Reservation's booking-setup).
*/

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Billing\Actions\RecordPayment;
use Modules\Billing\DTOs\NewPayment;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Enums\PaymentType;
use Modules\Billing\Events\PaymentReceived;
use Modules\Billing\Exceptions\PaymentNotAllowed;
use Modules\Billing\Models\Payment;
use Modules\Reservation\Actions\CancelReservation;
use Modules\Reservation\Enums\PaymentStatus;
use Modules\Reservation\Enums\ReservationLogAction;
use Modules\Reservation\Enums\ReservationStatus;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

require_once __DIR__.'/../../../Reservation/tests/Support/management-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
});

function pay(int $reservationId, string $amount, PaymentMethod $method = PaymentMethod::Cash): Payment
{
    return booking(fn (): Payment => RecordPayment::make()->handle(new NewPayment($reservationId, $method, $amount)));
}

it('confirms the booking automatically when a 30% deposit is taken', function (): void {
    $user = staffUser();
    $reservation = bookStay(['401']);
    expect([$reservation->status, $reservation->deposit_required])->toBe([ReservationStatus::Tentative, '6831.00']);

    get(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}"))->assertOk()->assertSeeHtml('data-take-payment')->assertSeeHtml('6831.00');
    post(tenantUrl('sunrise', '/billing/payments'), ['reservation_id' => $reservation->id, 'method' => 'card', 'amount' => '6831.00', 'reference' => 'VISA-4421'])
        ->assertRedirect(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}#payments"))->assertSessionHas('success');

    $confirmed = freshReservation($reservation->id);
    $payment = booking(fn (): Payment => Payment::query()->sole());

    expect($confirmed->status)->toBe(ReservationStatus::Confirmed)
        ->and($confirmed->confirmed_at)->not->toBeNull()
        ->and([$confirmed->amount_paid, $confirmed->balance_due, $confirmed->payment_status])->toBe(['6831.00', '15939.00', PaymentStatus::DepositPaid])
        ->and($confirmed->items->pluck('status')->unique()->all())->toBe([ReservationStatus::Confirmed])
        ->and($confirmed->logs->pluck('action')->reject(ReservationLogAction::EmailSent)->take(2)->values()->all())->toBe([ReservationLogAction::Confirmed, ReservationLogAction::PaymentApplied])
        ->and($payment->receipt_no)->toStartWith('PAY-')
        ->and([$payment->payment_type, $payment->method, $payment->amount, $payment->reference, $payment->received_by])->toBe([PaymentType::Deposit, PaymentMethod::Card, '6831.00', 'VISA-4421', $user->id]);

    get(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}"))->assertOk()->assertSeeHtml('data-payment="'.$payment->receipt_no.'"');
});

it('keeps a booking tentative until payments cover the deposit, then fully paid at the total', function (): void {
    $reservation = bookStay(['401']);

    pay($reservation->id, '3000.00');
    $after = freshReservation($reservation->id);
    expect([$after->status, $after->payment_status, $after->amount_paid])->toBe([ReservationStatus::Tentative, PaymentStatus::Unpaid, '3000.00']);

    pay($reservation->id, '3831.00', PaymentMethod::MobileWallet);
    $after = freshReservation($reservation->id);
    expect([$after->status, $after->amount_paid])->toBe([ReservationStatus::Confirmed, '6831.00']);

    pay($reservation->id, '15939.00', PaymentMethod::BankTransfer);
    $after = freshReservation($reservation->id);
    expect([$after->payment_status, $after->balance_due])->toBe([PaymentStatus::FullyPaid, '0.00']);
});

it('tells other modules the new total paid', function (): void {
    Event::fake([PaymentReceived::class]);
    $reservation = bookStay(['401']);

    pay($reservation->id, '1000.00');
    $payment = pay($reservation->id, '500.00');

    Event::assertDispatched(PaymentReceived::class, fn (PaymentReceived $event): bool => $event->paymentId === $payment->id
        && $event->reservationId === $reservation->id && $event->amount === '500.00' && $event->reservationPaidTotal === '1500.00');
});

it('refuses more than the balance due, and payments for a cancelled booking', function (): void {
    $reservation = bookStay(['401']);

    expect(fn () => pay($reservation->id, '22770.01'))->toThrow(PaymentNotAllowed::class);

    booking(fn () => CancelReservation::make()->handle($reservation, 'Guest cancelled'));
    expect(fn () => pay($reservation->id, '100.00'))->toThrow(PaymentNotAllowed::class)
        ->and(booking(fn (): int => Payment::query()->count()))->toBe(0);
});

it('validates the payment and shows the error on the payments tab', function (): void {
    staffUser();
    $reservation = bookStay(['401']);

    post(tenantUrl('sunrise', '/billing/payments'), ['reservation_id' => $reservation->id, 'method' => 'cheque', 'amount' => '-5'])
        ->assertSessionHasErrors(['method', 'amount']);
    post(tenantUrl('sunrise', '/billing/payments'), ['reservation_id' => $reservation->id, 'method' => 'cash', 'amount' => '99999.00'])
        ->assertRedirect(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}#payments"))->assertSessionHasErrors('amount', errorBag: 'payment');
    post(tenantUrl('sunrise', '/billing/payments'), ['reservation_id' => 999999, 'method' => 'cash', 'amount' => '10'])->assertSessionHasErrors('reservation_id');
});

it('is refused to staff without billing.payment.create', function (): void {
    staffUser(DefaultRole::HousekeepingSupervisor);
    $reservation = bookStay(['401']);

    post(tenantUrl('sunrise', '/billing/payments'), ['reservation_id' => $reservation->id, 'method' => 'cash', 'amount' => '100'])->assertForbidden();
});

it('downloads a PDF receipt', function (): void {
    staffUser();
    $payment = pay(bookStay(['401'])->id, '6831.00');

    $response = get(tenantUrl('sunrise', "/billing/payments/{$payment->id}/receipt"))->assertOk()->assertHeader('content-type', 'application/pdf');

    expect($response->headers->get('content-disposition'))->toContain($payment->receipt_no.'.pdf')
        ->and((string) $response->getContent())->toStartWith('%PDF');
});

it('keeps receipts private to the tenant and to staff who may view payments', function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'greenvalley'])));
    $theirs = booking(fn (): Payment => RecordPayment::make()->handle(new NewPayment(bookStay(['401'], slug: 'greenvalley')->id, PaymentMethod::Cash, '100.00')), 'greenvalley');
    staffUser();
    get(tenantUrl('sunrise', "/billing/payments/{$theirs->id}/receipt"))->assertNotFound();

    $ours = pay(bookStay(['402'])->id, '100.00');
    staffUser(DefaultRole::HousekeepingSupervisor);
    get(tenantUrl('sunrise', "/billing/payments/{$ours->id}/receipt"))->assertForbidden();
});
