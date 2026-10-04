<?php

/*
| Cashier shifts (Step 2.6): open with a float, payments taken meanwhile belong to the shift, close
| with a cash count; the variance needs a reason.
|
| Room 401: 6,000 a night + SC 10% + VAT 15% = 7,590.00; two nights = 15,180.00, deposit 30% = 4,554.00.
*/

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Billing\Enums\CashierShiftStatus;
use Modules\Billing\Models\CashierShift;
use Modules\Billing\Models\Payment;
use Modules\IAM\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\post;

require_once __DIR__.'/../../../Reservation/tests/Support/management-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    Notification::fake();
});

function openShiftOf(User $user): ?CashierShift
{
    return booking(fn (): ?CashierShift => CashierShift::query()->where('user_id', $user->id)->where('status', CashierShiftStatus::Open->value)->first());
}

it('opens a shift, collects the cash payments taken and closes with a balanced count', function (): void {
    $cashier = staffUser(DefaultRole::FrontDeskAgent);
    $reservation = bookStay(['401'], now()->addDays(3)->toDateString(), now()->addDays(5)->toDateString());

    get(tenantUrl('sunrise', '/billing/shifts/mine'))->assertOk()->assertSeeHtml('data-open-shift');
    post(tenantUrl('sunrise', '/billing/shifts'), ['opening_float' => '5000'])->assertSessionHas('success');
    post(tenantUrl('sunrise', '/billing/shifts'), ['opening_float' => '5000'])->assertSessionHas('error');

    post(tenantUrl('sunrise', '/billing/payments'), ['reservation_id' => $reservation->id, 'method' => 'cash', 'amount' => '4000'])->assertSessionHas('success');
    post(tenantUrl('sunrise', '/billing/payments'), ['reservation_id' => $reservation->id, 'method' => 'card', 'amount' => '554'])->assertSessionHas('success');

    $shift = openShiftOf($cashier);
    $payments = booking(fn () => Payment::query()->orderBy('id')->get());
    expect($payments->pluck('cashier_shift_id')->all())->toBe([$shift->id, $shift->id])
        ->and($payments[0]->business_date?->toDateString())->toBe(booking(fn () => $shift->business_date->toDateString()));

    get(tenantUrl('sunrise', '/billing/shifts/mine'))->assertOk()->assertSeeHtml('data-shift-open="'.$shift->id.'"')->assertSee('9,000.00');

    // 9,000 expected (5,000 float + 4,000 cash; the card payment is not cash).
    post(tenantUrl('sunrise', "/billing/shifts/{$shift->id}/close"), ['count' => ['1000' => 8, '500' => 2]])->assertRedirect(tenantUrl('sunrise', "/billing/shifts/{$shift->id}"));

    $closed = booking(fn (): CashierShift => $shift->fresh());
    expect([$closed->status, $closed->cash_received, $closed->expected_cash, $closed->counted_cash, $closed->cash_variance, $closed->open_user_id])
        ->toBe([CashierShiftStatus::Closed, '4000.00', '9000.00', '9000.00', '0.00', null])
        ->and($closed->denominations)->toBe(['1000' => 8, '500' => 2]);

    get(tenantUrl('sunrise', "/billing/shifts/{$shift->id}"))->assertOk()->assertSeeHtml('data-variance>0.00<');

    // A payment after closing belongs to no shift; a new shift can be opened.
    post(tenantUrl('sunrise', '/billing/payments'), ['reservation_id' => $reservation->id, 'method' => 'cash', 'amount' => '100'])->assertSessionHas('success');
    expect(booking(fn () => Payment::query()->latest('id')->first()->cashier_shift_id))->toBeNull();
    post(tenantUrl('sunrise', '/billing/shifts'), ['opening_float' => '2000'])->assertSessionHas('success');
});

it('needs a reason when the count is off, and subtracts cash refunds', function (): void {
    $cashier = staffUser(DefaultRole::FrontOfficeManager);
    $reservation = bookStay(['401'], now()->addDays(3)->toDateString(), now()->addDays(5)->toDateString());
    post(tenantUrl('sunrise', '/billing/shifts'), ['opening_float' => '1000']);
    post(tenantUrl('sunrise', '/billing/payments'), ['reservation_id' => $reservation->id, 'method' => 'cash', 'amount' => '4554'])->assertSessionHas('success');
    post(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}/cancel"), ['reason' => 'Plans changed'])->assertSessionHas('success');
    post(tenantUrl('sunrise', '/billing/refunds'), ['reservation_id' => $reservation->id, 'kind' => 'cancellation', 'method' => 'cash', 'amount' => '554', 'reason' => 'Part refund'])->assertSessionHas('success');
    $shift = openShiftOf($cashier);

    // Expected 1,000 + 4,554 − 554 = 5,000; counted 4,900.
    post(tenantUrl('sunrise', "/billing/shifts/{$shift->id}/close"), ['count' => ['1000' => 4, '500' => 1, '100' => 4]])->assertSessionHas('error');
    expect(booking(fn () => $shift->fresh()->status))->toBe(CashierShiftStatus::Open);

    post(tenantUrl('sunrise', "/billing/shifts/{$shift->id}/close"), ['count' => ['1000' => 4, '500' => 1, '100' => 4], 'variance_reason' => 'Change given twice'])->assertSessionHas('success');
    $closed = booking(fn (): CashierShift => $shift->fresh());
    expect([$closed->cash_refunded, $closed->expected_cash, $closed->cash_variance, $closed->variance_reason])->toBe(['554.00', '5000.00', '-100.00', 'Change given twice']);

    post(tenantUrl('sunrise', "/billing/shifts/{$shift->id}/close"), ['count' => ['1000' => 5]])->assertForbidden();
    post(tenantUrl('sunrise', "/billing/shifts/{$shift->id}/close"), ['count' => ['1000' => -1]])->assertForbidden();
});

it('lets only the cashier close their shift; managers see every shift', function (): void {
    $cashier = staffUser(DefaultRole::FrontDeskAgent);
    post(tenantUrl('sunrise', '/billing/shifts'), ['opening_float' => '500']);
    $shift = openShiftOf($cashier);
    get(tenantUrl('sunrise', '/billing/shifts'))->assertForbidden();
    post(tenantUrl('sunrise', "/billing/shifts/{$shift->id}/close"), ['count' => ['abc' => 'x']])->assertSessionHasErrors('count.abc');

    staffUser(DefaultRole::Accountant);
    get(tenantUrl('sunrise', '/billing/shifts'))->assertOk();
    getJson(tenantUrl('sunrise', '/billing/shifts/data?draw=1&start=0&length=10'))->assertOk()->assertJsonPath('recordsTotal', 1);
    get(tenantUrl('sunrise', "/billing/shifts/{$shift->id}"))->assertOk()->assertSee('Shift report (X, shift still open)');
    get(tenantUrl('sunrise', '/billing/shifts/mine'))->assertForbidden();
    post(tenantUrl('sunrise', "/billing/shifts/{$shift->id}/close"), ['count' => ['500' => 1]])->assertForbidden();

    $other = staffUser(DefaultRole::FrontDeskAgent);
    get(tenantUrl('sunrise', "/billing/shifts/{$shift->id}"))->assertForbidden();
    post(tenantUrl('sunrise', "/billing/shifts/{$shift->id}/close"), ['count' => ['500' => 1]])->assertForbidden();
    expect(openShiftOf($other))->toBeNull();

    actingAs($cashier);
    post(tenantUrl('sunrise', "/billing/shifts/{$shift->id}/close"), ['count' => ['500' => 1]])->assertSessionHas('success');
});

it('does not show another tenant\'s shift', function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'greenvalley'])));
    $theirs = booking(fn (): CashierShift => CashierShift::factory()->open()->create(), 'greenvalley');
    staffUser(DefaultRole::Accountant);

    get(tenantUrl('sunrise', "/billing/shifts/{$theirs->id}"))->assertNotFound();
    getJson(tenantUrl('sunrise', '/billing/shifts/data?draw=1&start=0&length=10'))->assertOk()->assertJsonPath('recordsTotal', 0);
});
