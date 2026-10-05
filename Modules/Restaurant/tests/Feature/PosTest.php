<?php

/*
| POS layout, sessions & manager PIN (Step 3.3). "Done when": a cashier opens a session on a tablet,
| switches user by PIN, and closes the session with a Z report.
*/
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Modules\IAM\Contracts\PosPins;
use Modules\IAM\Models\User;
use Modules\Property\Models\Property;
use Modules\Restaurant\Actions\RegisterTerminal;
use Modules\Restaurant\Enums\PosSessionStatus;
use Modules\Restaurant\Models\ManagerApproval;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Models\PosSession;
use Modules\Restaurant\Models\PosTerminal;
use Modules\Restaurant\Services\PosDevice;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;
use function Pest\Laravel\put;
use function Pest\Laravel\travel;
use function Pest\Laravel\withCookie;

require_once __DIR__.'/../Support/pos-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    Notification::fake();
});

it('registers a tablet with its device token, and forgets it when the token changes', function (): void {
    [$terminal, $token] = posTerminal();

    get(tenantUrl('sunrise', '/pos'))->assertRedirect(tenantUrl('sunrise', '/pos/register'));
    get(tenantUrl('sunrise', '/pos/register'))->assertOk()->assertSeeHtml('data-register-device');
    post(tenantUrl('sunrise', '/pos/register'), ['token' => 'NOPE-NOPE'])->assertSessionHasErrors('token');
    post(tenantUrl('sunrise', '/pos/register'), ['token' => strtolower($token)])->assertRedirect(tenantUrl('sunrise', '/pos'))
        ->assertCookie(PosDevice::COOKIE, $terminal->id.'|'.hash('sha256', $token));

    onDevice($terminal);
    get(tenantUrl('sunrise', '/pos'))->assertOk()->assertSeeHtml('data-lock-screen');

    $old = booking(fn (): PosTerminal => PosTerminal::query()->findOrFail($terminal->id))->device_token;
    booking(fn () => RegisterTerminal::make()->newToken($terminal));
    withCookie(PosDevice::COOKIE, $terminal->id.'|'.$old);
    get(tenantUrl('sunrise', '/pos'))->assertRedirect(tenantUrl('sunrise', '/pos/register'));

    booking(fn () => PosTerminal::query()->whereKey($terminal->id)->update(['is_active' => false]));
    onDevice($terminal);
    get(tenantUrl('sunrise', '/pos'))->assertRedirect(tenantUrl('sunrise', '/pos/register'));
});

it('lists only the staff who may work here, and signs them in by PIN', function (): void {
    [$terminal] = posTerminal();
    $cashier = posStaff(DefaultRole::OutletCashier, '2222', $terminal);
    $waiter = posStaff(DefaultRole::Waiter, '1111', $terminal);
    $gm = posStaff(DefaultRole::GeneralManager, '8888', $terminal, outlet: false);
    $chef = posStaff(DefaultRole::Chef, '4444', $terminal);
    $elsewhere = posStaff(DefaultRole::Waiter, '5555', $terminal, outlet: false);
    onDevice($terminal);

    get(tenantUrl('sunrise', '/pos'))->assertOk()
        ->assertSeeHtml('data-staff="'.$cashier->email.'"')->assertSeeHtml('data-staff="'.$waiter->email.'"')->assertSeeHtml('data-staff="'.$gm->email.'"')
        ->assertDontSeeHtml('data-staff="'.$chef->email.'"')->assertDontSeeHtml('data-staff="'.$elsewhere->email.'"');

    pinSignIn($cashier, '9999')->assertSessionHasErrors('pin');
    pinSignIn($elsewhere, '5555')->assertSessionHasErrors('pin');
    expect(Auth::check())->toBeFalse();

    pinSignIn($cashier, '2222')->assertRedirect(tenantUrl('sunrise', '/pos/main'));
    expect(Auth::id())->toBe($cashier->id);
    get(tenantUrl('sunrise', '/pos/main'))->assertOk()->assertSee($cashier->name)->assertSeeHtml('data-open-session');
});

it('locks a person out after five wrong PINs on the terminal', function (): void {
    [$terminal] = posTerminal();
    $waiter = posStaff(DefaultRole::Waiter, '1111', $terminal);
    onDevice($terminal);

    foreach (range(1, 5) as $attempt) {
        pinSignIn($waiter, '0000')->assertSessionHasErrors(['pin' => 'Wrong PIN.']);
    }

    pinSignIn($waiter, '1111')->assertSessionHasErrors('pin');
    expect(Auth::check())->toBeFalse();
});

it('does not sign anyone in by PIN without a registered device', function (): void {
    [$terminal] = posTerminal();
    $waiter = posStaff(DefaultRole::Waiter, '1111', $terminal);

    pinSignIn($waiter, '1111')->assertRedirect(tenantUrl('sunrise', '/pos/register'));
    get(tenantUrl('sunrise', '/pos/main'))->assertRedirect(tenantUrl('sunrise', '/pos/register'));
    expect(Auth::check())->toBeFalse();
});

it('opens a session, switches user by PIN and closes it with a Z report', function (): void {
    [$terminal] = posTerminal();
    $cashier = posStaff(DefaultRole::OutletCashier, '2222', $terminal);
    $waiter = posStaff(DefaultRole::Waiter, '1111', $terminal);
    onDevice($terminal);

    pinSignIn($cashier, '2222');
    post(tenantUrl('sunrise', '/pos/session'), ['opening_float' => '3000'])->assertSessionHas('success');
    post(tenantUrl('sunrise', '/pos/session'), ['opening_float' => '3000'])->assertSessionHas('error', 'This terminal already has an open session.');
    $session = booking(fn (): PosSession => PosSession::query()->sole());
    expect([$session->opening_float, $session->opened_by, $session->business_date->toDateString()])
        ->toBe(['3000.00', $cashier->id, booking(fn (): string => Property::query()->where('code', 'CXB')->sole()->business_date->toDateString())]);
    get(tenantUrl('sunrise', "/pos/sessions/{$session->id}/report"))->assertOk()->assertSeeHtml('data-session-report="x"');

    // Switch user: the waiter takes over; they may not close the session.
    post(tenantUrl('sunrise', '/pos/lock'))->assertRedirect(tenantUrl('sunrise', '/pos'));
    expect(Auth::check())->toBeFalse();
    pinSignIn($waiter, '1111')->assertRedirect(tenantUrl('sunrise', '/pos/main'));
    get(tenantUrl('sunrise', '/pos/main'))->assertOk()->assertSeeHtml('data-session-open')->assertDontSeeHtml('data-close-session');
    post(tenantUrl('sunrise', '/pos/session/close'), ['count' => ['1000' => 3]])->assertForbidden();

    post(tenantUrl('sunrise', '/pos/lock'));
    pinSignIn($cashier, '2222');
    post(tenantUrl('sunrise', '/pos/session/close'), ['count' => ['1000' => 2, '500' => 1, '100' => 4]])->assertSessionHas('error');
    post(tenantUrl('sunrise', '/pos/session/close'), ['count' => ['1000' => 2, '500' => 1, '100' => 4], 'variance_reason' => 'Short at handover'])
        ->assertRedirect(tenantUrl('sunrise', "/pos/sessions/{$session->id}/report"));

    $closed = booking(fn (): PosSession => $session->fresh() ?? throw new RuntimeException);
    expect([$closed->status, $closed->counted_cash, $closed->cash_variance, $closed->open_terminal_id])->toBe([PosSessionStatus::Closed, '2900.00', '-100.00', null]);
    get(tenantUrl('sunrise', "/pos/sessions/{$session->id}/report"))->assertOk()->assertSeeHtml('data-session-report="z"')->assertSeeHtml('data-variance>-100.00<');
});

it('needs a manager\'s PIN to close with a large difference, used once', function (): void {
    [$terminal] = posTerminal();
    $cashier = posStaff(DefaultRole::OutletCashier, '2222', $terminal);
    $waiter = posStaff(DefaultRole::Waiter, '1111', $terminal);
    $manager = posStaff(DefaultRole::FnbManager, '9999', $terminal);
    onDevice($terminal);
    pinSignIn($cashier, '2222');
    post(tenantUrl('sunrise', '/pos/session'), ['opening_float' => '3000']);
    $session = booking(fn (): PosSession => PosSession::query()->sole());
    $close = ['count' => ['1000' => 2], 'variance_reason' => 'Drawer short'];
    $approve = fn (array $data): TestResponse => postJson(tenantUrl('sunrise', '/pos/api/approvals'),
        ['action' => 'session.close-variance', 'subject_id' => $session->id, ...$data]);

    get(tenantUrl('sunrise', '/pos/main'))->assertOk()->assertSeeHtml('data-approver="'.$manager->id.'"')->assertDontSeeHtml('data-approver="'.$waiter->id.'"');
    post(tenantUrl('sunrise', '/pos/session/close'), $close)->assertSessionHas('error', "The difference of -1000.00 needs a manager's approval.");

    $approve(['manager_id' => $manager->id, 'pin' => '0000'])->assertUnprocessable()->assertJsonPath('message', 'Wrong PIN.');
    $approve(['manager_id' => $waiter->id, 'pin' => '1111'])->assertUnprocessable()->assertJsonPath('message', 'This person cannot approve it.');
    $approve(['manager_id' => $manager->id, 'pin' => '9999', 'action' => 'drawer.open'])->assertUnprocessable()->assertJsonValidationErrors('action');
    $approvalId = $approve(['manager_id' => $manager->id, 'pin' => '9999'])->assertOk()->json('approval_id');

    post(tenantUrl('sunrise', '/pos/session/close'), [...$close, 'approval_id' => $approvalId])->assertRedirect(tenantUrl('sunrise', "/pos/sessions/{$session->id}/report"));

    $approval = booking(fn (): ManagerApproval => ManagerApproval::query()->sole());
    expect([$approval->approved_by, $approval->requested_by, $approval->subject_id, $approval->used_at !== null])->toBe([$manager->id, $cashier->id, $session->id, true])
        ->and(booking(fn () => $session->fresh()?->manager_approval_id))->toBe($approval->id);
    get(tenantUrl('sunrise', "/pos/sessions/{$session->id}/report"))->assertSeeHtml('data-approved-by');

    // A used approval does not open another door.
    post(tenantUrl('sunrise', '/pos/session'), ['opening_float' => '3000']);
    $next = booking(fn (): PosSession => PosSession::query()->where('status', 'open')->sole());
    post(tenantUrl('sunrise', '/pos/session/close'), [...$close, 'approval_id' => $approvalId])->assertSessionHas('error');
    expect(booking(fn () => $next->fresh()?->status))->toBe(PosSessionStatus::Open);
});

it('locks the terminal after the idle time', function (): void {
    [$terminal] = posTerminal();
    $waiter = posStaff(DefaultRole::Waiter, '1111', $terminal);
    onDevice($terminal);
    pinSignIn($waiter, '1111');
    get(tenantUrl('sunrise', '/pos/main'))->assertOk();

    travel(4)->minutes();
    get(tenantUrl('sunrise', '/pos/main'))->assertRedirect(tenantUrl('sunrise', '/pos'));
    expect(Auth::check())->toBeFalse();
});

it('lets people set their own POS PIN, unique in the company', function (): void {
    $waiter = staffUser(DefaultRole::Waiter);
    $other = tenantUserAs(tenant('sunrise'), DefaultRole::Waiter);
    booking(fn () => app(PosPins::class)->set($other->id, '4321'));
    actingAs($waiter);

    put(tenantUrl('sunrise', '/iam/profile/pos-pin'), ['pin' => '1234', 'pin_confirmation' => '1234', 'current_password' => 'wrong'])->assertSessionHasErrors('current_password', null, 'posPin');
    put(tenantUrl('sunrise', '/iam/profile/pos-pin'), ['pin' => '12', 'pin_confirmation' => '12', 'current_password' => 'Password123'])->assertSessionHasErrors('pin', null, 'posPin');
    put(tenantUrl('sunrise', '/iam/profile/pos-pin'), ['pin' => '4321', 'pin_confirmation' => '4321', 'current_password' => 'Password123'])->assertSessionHasErrors('pin', null, 'posPin');
    put(tenantUrl('sunrise', '/iam/profile/pos-pin'), ['pin' => '1234', 'pin_confirmation' => '1234', 'current_password' => 'Password123'])->assertSessionHas('success');

    expect(booking(fn (): bool => app(PosPins::class)->matches($waiter->id, '1234')))->toBeTrue()
        ->and(booking(fn () => User::query()->findOrFail($waiter->id)->pos_pin))->not->toContain('1234');
    get(tenantUrl('sunrise', '/iam/profile'))->assertOk()->assertSeeHtml('data-pos-pin');

    put(tenantUrl('sunrise', '/iam/profile/pos-pin'), ['remove' => '1', 'current_password' => 'Password123'])->assertSessionHas('success');
    expect(booking(fn (): bool => app(PosPins::class)->has($waiter->id)))->toBeFalse();
});

it('shows every session to managers in the back office', function (): void {
    [$terminal] = posTerminal();
    $session = booking(fn (): PosSession => PosSession::factory()->create(['outlet_id' => $terminal->outlet_id, 'pos_terminal_id' => $terminal->id]));

    staffUser(DefaultRole::FnbManager);
    get(tenantUrl('sunrise', '/restaurant/sessions'))->assertOk();
    getJson(tenantUrl('sunrise', '/restaurant/sessions/data?draw=1&start=0&length=10'))->assertOk()->assertJsonPath('recordsTotal', 1);
    get(tenantUrl('sunrise', "/restaurant/sessions/{$session->id}/report"))->assertOk()->assertSeeHtml('data-session-report="z"');

    staffUser(DefaultRole::Waiter);
    get(tenantUrl('sunrise', '/restaurant/sessions'))->assertForbidden();
});

it('does not take another company\'s device token', function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'greenvalley'])));
    [, $theirs] = booking(fn (): array => RegisterTerminal::make()->handle(Outlet::factory()->create(), 'Their tablet'), 'greenvalley');

    post(tenantUrl('sunrise', '/pos/register'), ['token' => $theirs])->assertSessionHasErrors('token');
});
