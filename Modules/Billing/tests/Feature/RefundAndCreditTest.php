<?php

/*
| Refunds and credit notes (Step 2.3).
*/

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Billing\Actions\IssueCreditNote;
use Modules\Billing\Actions\IssueInvoice;
use Modules\Billing\Actions\RecordPayment;
use Modules\Billing\Actions\RefundPayment;
use Modules\Billing\DTOs\NewPayment;
use Modules\Billing\DTOs\NewRefund;
use Modules\Billing\Enums\CityLedgerStatus;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Enums\PaymentType;
use Modules\Billing\Enums\RefundKind;
use Modules\Billing\Exceptions\PaymentNotAllowed;
use Modules\Billing\Models\CityLedgerEntry;
use Modules\Billing\Models\Folio;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Guest\Models\Company;
use Modules\Reservation\Actions\CancelReservation;
use Modules\Reservation\Enums\PaymentStatus;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

require_once __DIR__.'/../../../Reservation/tests/Support/management-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    Notification::fake();
});

function refund(int $reservationId, RefundKind $kind, string $amount, ?int $source = null): Payment
{
    return booking(fn (): Payment => RefundPayment::make()->handle(new NewRefund($reservationId, $kind, PaymentMethod::Cash, $amount, 'Test refund', $source)));
}

it('refunds a cancelled booking what it paid above the fee', function (): void {
    $reservation = bookStay(['401']);
    booking(fn () => RecordPayment::make()->handle(new NewPayment($reservation->id, PaymentMethod::Card, '6831.00')));
    booking(fn () => CancelReservation::make()->handle(freshReservation($reservation->id), 'Plans changed'));

    expect(fn () => refund($reservation->id, RefundKind::Cancellation, '6831.01'))->toThrow(PaymentNotAllowed::class);

    refund($reservation->id, RefundKind::Cancellation, '3000.00');
    $partly = freshReservation($reservation->id);
    expect([$partly->amount_paid, $partly->payment_status])->toBe(['3831.00', PaymentStatus::PartiallyRefunded]);

    refund($reservation->id, RefundKind::Cancellation, '3831.00');
    $refunded = freshReservation($reservation->id);
    expect([$refunded->amount_paid, $refunded->payment_status])->toBe(['0.00', PaymentStatus::Refunded])
        ->and(booking(fn (): string => Folio::query()->where('reservation_id', $reservation->id)->sole()->balance))->toBe('0.00');
});

it('refuses a cancellation refund for a booking that is not cancelled', function (): void {
    refund(bookStay(['401'])->id, RefundKind::Cancellation, '1.00');
})->throws(PaymentNotAllowed::class);

it('returns a security deposit, never more than was held', function (): void {
    $reservation = bookStay(['401']);
    $deposit = booking(fn (): Payment => RecordPayment::make()->handle(new NewPayment($reservation->id, PaymentMethod::Cash, '5000.00', securityDeposit: true)));

    refund($reservation->id, RefundKind::SecurityDeposit, '3000.00', $deposit->id);
    expect(fn () => refund($reservation->id, RefundKind::SecurityDeposit, '2000.01', $deposit->id))->toThrow(PaymentNotAllowed::class);
    refund($reservation->id, RefundKind::SecurityDeposit, '2000.00', $deposit->id);

    expect(booking(fn (): int => Payment::query()->where('refunded_payment_id', $deposit->id)->count()))->toBe(2)
        ->and(freshReservation($reservation->id)->amount_paid)->toBe('0.00');
});

it('credits an invoice on the city ledger first, then makes the rest refundable', function (): void {
    $reservation = bookStay(['401']);
    $folio = booking(fn (): Folio => Folio::query()->where('reservation_id', $reservation->id)->sole());
    $company = booking(fn (): Company => Company::factory()->create());
    $invoice = booking(function () use ($folio, $company): Invoice {
        $invoice = Invoice::factory()->create(['folio_id' => $folio->id, 'reservation_id' => $folio->reservation_id, 'total' => '10000.00', 'paid' => '4000.00', 'on_account' => '6000.00']);
        CityLedgerEntry::factory()->create(['company_id' => $company->id, 'folio_id' => $folio->id, 'invoice_id' => $invoice->id, 'amount' => '6000.00']);

        return $invoice;
    });

    $note = booking(fn () => IssueCreditNote::make()->handle($invoice, '7000.00', 'Room not as described'));

    expect($note->credit_note_no)->toStartWith('CN-')
        ->and([$note->applied_to_ledger, $note->refund_due])->toBe(['6000.00', '1000.00'])
        ->and(booking(fn (): CityLedgerStatus => CityLedgerEntry::query()->sole()->status))->toBe(CityLedgerStatus::Paid)
        ->and(booking(fn (): array => [Invoice::query()->findOrFail($invoice->id)->credited, Invoice::query()->findOrFail($invoice->id)->status]))->toBe(['7000.00', InvoiceStatus::PartiallyCredited]);

    refund($reservation->id, RefundKind::CreditNote, '1000.00', $note->id);
    expect(fn () => refund($reservation->id, RefundKind::CreditNote, '0.01', $note->id))->toThrow(PaymentNotAllowed::class)
        ->and(fn () => booking(fn () => IssueCreditNote::make()->handle($invoice, '3000.01', 'Too much')))->toThrow(PaymentNotAllowed::class);
});

it('never changes or deletes an issued invoice', function (): void {
    $invoice = booking(fn (): Invoice => IssueInvoice::make()->handle(Folio::query()->where('reservation_id', bookStay(['401'])->id)->sole()));

    expect(fn () => booking(fn () => $invoice->forceFill(['total' => '1.00'])->save()))->toThrow(LogicException::class)
        ->and(fn () => booking(fn () => $invoice->delete()))->toThrow(LogicException::class);
});

it('lets only managers refund and credit, and shows the Pay back card', function (): void {
    $reservation = bookStay(['401']);
    booking(fn () => RecordPayment::make()->handle(new NewPayment($reservation->id, PaymentMethod::Cash, '5000.00', securityDeposit: true)));

    staffUser(DefaultRole::FrontDeskAgent);
    post(tenantUrl('sunrise', '/billing/refunds'), ['reservation_id' => $reservation->id, 'kind' => 'security_deposit', 'method' => 'cash', 'amount' => '10', 'reason' => 'x'])->assertForbidden();
    get(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}"))->assertDontSeeHtml('data-refunds');

    staffUser(DefaultRole::FrontOfficeManager);
    get(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}"))->assertSeeHtml('data-refund="security_deposit"');
    post(tenantUrl('sunrise', '/billing/refunds'), ['reservation_id' => $reservation->id, 'kind' => 'security_deposit', 'method' => 'cash', 'amount' => '10', 'reason' => ''])
        ->assertSessionHasErrors('reason');
});

it('keeps security deposits out of what the booking has paid', function (): void {
    $reservation = bookStay(['401']);
    booking(fn () => RecordPayment::make()->handle(new NewPayment($reservation->id, PaymentMethod::Cash, '5000.00', securityDeposit: true)));

    expect(booking(fn (): string => Payment::query()->sole()->payment_type->value))->toBe(PaymentType::SecurityDeposit->value)
        ->and(freshReservation($reservation->id)->amount_paid)->toBe('0.00');
});
