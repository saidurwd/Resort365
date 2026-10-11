<?php

/*
| Refund approvals (Step 5.1). "Done when": a refund above the threshold waits for the General Manager's approval and is
| paid only after it.
|
| Room 401 for 2 nights: 6,831.00 paid by card, then the booking is cancelled (no fee).
*/
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Modules\Billing\Actions\RecordPayment;
use Modules\Billing\Actions\RefundPayment;
use Modules\Billing\DTOs\NewPayment;
use Modules\Billing\DTOs\NewRefund;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Enums\PaymentStatus;
use Modules\Billing\Enums\RefundKind;
use Modules\Billing\Models\Payment;
use Modules\Core\Actions\SaveApprovalWorkflow;
use Modules\Core\Actions\SeedApprovalWorkflows;
use Modules\Core\Enums\ApprovalStatus;
use Modules\Core\Models\ApprovalRequest;
use Modules\Core\Models\ApprovalWorkflow;
use Modules\Reservation\Actions\CancelReservation;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

require_once __DIR__.'/../../../Reservation/tests/Support/management-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    booking(fn () => app(SeedApprovalWorkflows::class)->handle());
    Notification::fake();
});

/** A cancelled booking that paid 6,831.00. */
function cancelledPaid(): int
{
    $reservation = bookStay(['401']);
    booking(fn () => RecordPayment::make()->handle(new NewPayment($reservation->id, PaymentMethod::Card, '6831.00')));
    booking(fn () => CancelReservation::make()->handle(freshReservation($reservation->id), 'Plans changed'));

    return $reservation->id;
}

/**
 * @return TestResponse<Response>
 */
function askRefund(int $reservationId, string $amount, string $reason = 'Cancelled'): TestResponse
{
    return post(tenantUrl('sunrise', '/billing/refunds'), ['reservation_id' => $reservationId, 'kind' => 'cancellation', 'method' => 'card', 'amount' => $amount, 'reason' => $reason]);
}

function refundsPaid(int $reservationId): int
{
    return booking(fn (): int => Payment::query()->where('reservation_id', $reservationId)->where('payment_type', 'refund')->count());
}

function refundRequest(): ApprovalRequest
{
    return booking(fn (): ApprovalRequest => ApprovalRequest::query()->where('document_type', 'refund')->latest('id')->firstOrFail());
}

it('pays a refund below the approval amount at once', function (): void {
    $id = cancelledPaid();
    staffUser(DefaultRole::FrontOfficeManager);

    askRefund($id, '3000.00')->assertSessionHas('success');

    expect(refundsPaid($id))->toBe(1)->and(booking(fn (): int => ApprovalRequest::query()->count()))->toBe(0);
});

it('holds a refund above the approval amount until the General Manager approves, then pays it (done when)', function (): void {
    $id = cancelledPaid();
    $requester = staffUser(DefaultRole::FrontOfficeManager);

    askRefund($id, '6831.00', 'Guest could not travel')->assertSessionHas('success', fn (string $message): bool => str_contains($message, 'needs approval'));
    $request = refundRequest();

    expect(refundsPaid($id))->toBe(0)->and($request->status)->toBe(ApprovalStatus::Pending)->and($request->amount)->toBe('6831.00')->and($request->requested_by)->toBe($requester->id)
        ->and(array_column($request->steps, 'label'))->toBe(['General Manager']);
    get(tenantUrl('sunrise', "/reservation/bookings/{$id}"))->assertOk()->assertSeeHtml('data-refund-approval="'.$request->id.'"');

    // The requester cannot approve it, nor can a front office manager without the permission.
    post(tenantUrl('sunrise', "/core/approvals/{$request->id}/approve"))->assertForbidden();
    expect(refundsPaid($id))->toBe(0);

    $gm = staffUser(DefaultRole::GeneralManager);
    get(tenantUrl('sunrise', '/core/approvals'))->assertOk()->assertSeeHtml('data-approval="'.$request->id.'"');
    post(tenantUrl('sunrise', "/core/approvals/{$request->id}/approve"), ['comment' => 'Fine'])->assertSessionHas('success');

    $refund = booking(fn (): Payment => Payment::query()->where('reservation_id', $id)->where('payment_type', 'refund')->sole());
    expect($refund->amount)->toBe('6831.00')->and($refund->status)->toBe(PaymentStatus::Succeeded)->and($refund->refund_kind)->toBe(RefundKind::Cancellation)->and($refund->received_by)->toBe($requester->id)
        ->and(refundRequest()->status)->toBe(ApprovalStatus::Approved)->and(freshReservation($id)->amount_paid)->toBe('0.00')
        ->and(booking(fn (): int => ApprovalRequest::query()->firstOrFail()->actions()->where('user_id', $gm->id)->count()))->toBe(1);
});

it('pays nothing when the refund is rejected', function (): void {
    $id = cancelledPaid();
    staffUser(DefaultRole::FrontOfficeManager);
    askRefund($id, '6000.00');
    $request = refundRequest();

    staffUser(DefaultRole::GeneralManager);
    post(tenantUrl('sunrise', "/core/approvals/{$request->id}/reject"), ['comment' => 'Fee applies'])->assertSessionHas('success');

    expect(refundsPaid($id))->toBe(0)->and(refundRequest()->status)->toBe(ApprovalStatus::Rejected);
});

it('does not let the same money be asked for twice while a request waits', function (): void {
    $id = cancelledPaid();
    staffUser(DefaultRole::FrontOfficeManager);
    askRefund($id, '6000.00')->assertSessionHas('success');

    askRefund($id, '900.00')->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'At most 831.00'));
    askRefund($id, '800.00')->assertSessionHas('success');
    expect(booking(fn (): int => ApprovalRequest::query()->count()))->toBe(1)->and(refundsPaid($id))->toBe(1);
});

it('refuses to pay an approved refund that can no longer be paid, leaving the request waiting', function (): void {
    $id = cancelledPaid();
    staffUser(DefaultRole::FrontOfficeManager);
    askRefund($id, '6831.00');
    $request = refundRequest();

    // Meanwhile 2,000 was refunded directly, so the full amount is no longer there.
    booking(fn () => RefundPayment::make()->handle(new NewRefund($id, RefundKind::Cancellation, PaymentMethod::Cash, '2000.00', 'Part now')));

    staffUser(DefaultRole::GeneralManager);
    post(tenantUrl('sunrise', "/core/approvals/{$request->id}/approve"))->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'can no longer be paid'));

    expect(refundRequest()->status)->toBe(ApprovalStatus::Pending)->and(refundsPaid($id))->toBe(1);
});

it('follows the tenant\'s workflow: a lower amount makes smaller refunds wait', function (): void {
    $id = cancelledPaid();
    booking(fn () => app(SaveApprovalWorkflow::class)->handle(ApprovalWorkflow::query()->where('document_type', 'refund')->firstOrFail(), true, [['label' => 'General Manager', 'permission' => 'billing.refund.approve', 'min_amount' => '1000']]));
    staffUser(DefaultRole::FrontOfficeManager);

    askRefund($id, '3000.00')->assertSessionHas('success', fn (string $message): bool => str_contains($message, 'needs approval'));
    expect(refundsPaid($id))->toBe(0);

    booking(fn () => ApprovalWorkflow::query()->where('document_type', 'refund')->update(['is_active' => false]));
    askRefund($id, '1000.00')->assertSessionHas('success', fn (string $message): bool => str_contains($message, 'paid'));
    expect(refundsPaid($id))->toBe(1);
});

it('lets the General Manager pay a big refund after another approver, but never their own', function (): void {
    $id = cancelledPaid();
    $gm = staffUser(DefaultRole::GeneralManager);
    askRefund($id, '6831.00');
    $request = refundRequest();

    expect($request->requested_by)->toBe($gm->id);
    post(tenantUrl('sunrise', "/core/approvals/{$request->id}/approve"))->assertForbidden();
    staffUser(DefaultRole::GeneralManager);
    post(tenantUrl('sunrise', "/core/approvals/{$request->id}/approve"))->assertSessionHas('success');
    expect(refundsPaid($id))->toBe(1);
});
