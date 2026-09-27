<?php

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\IAM\Models\User;
use PragmaRX\Google2FA\Google2FA;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\withSession;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    tenantUser(Tenant::factory()->create(['slug' => 'sunrise']), ['email' => 'owner@sunrise.test']);
});

function freshUser(Tenant $tenant, User $user): User
{
    return app(TenantContext::class)->run($tenant, fn (): User => $user->fresh() ?? throw new RuntimeException('User vanished'));
}

/**
 * TOTP for the given secret, `$slot` periods from now (Fortify refuses a code it has already accepted).
 */
function totp(string $secret, int $slot = 0): string
{
    $google2fa = new Google2FA;

    return $google2fa->oathTotp($secret, $google2fa->getTimestamp() + $slot);
}

function enableTwoFactor(Tenant $tenant, User $user): string
{
    actingAs($user);
    withSession(['auth.password_confirmed_at' => time()]);

    post(tenantUrl('sunrise', '/user/two-factor-authentication'))->assertSessionHas('status', 'two-factor-authentication-enabled');

    $secret = decrypt((string) freshUser($tenant, $user)->two_factor_secret);

    post(tenantUrl('sunrise', '/user/confirmed-two-factor-authentication'), ['code' => totp($secret)])
        ->assertSessionHas('status', 'two-factor-authentication-confirmed');

    return $secret;
}

it('asks for the password before enabling two-factor authentication', function (): void {
    actingAs(userIn('sunrise', 'owner@sunrise.test'));

    post(tenantUrl('sunrise', '/user/two-factor-authentication'))->assertRedirect(tenantUrl('sunrise', '/user/confirm-password'));
    get(tenantUrl('sunrise', '/user/confirm-password'))->assertOk();
});

it('enables, confirms and shows setup details and recovery codes on the profile', function (): void {
    actingAs(userIn('sunrise', 'owner@sunrise.test'));
    withSession(['auth.password_confirmed_at' => time()]);
    post(tenantUrl('sunrise', '/user/two-factor-authentication'));

    get(tenantUrl('sunrise', '/iam/profile'))->assertOk()->assertSeeHtml('data-two-factor-qr')->assertSeeHtml('data-two-factor-secret');

    $secret = decrypt((string) freshUser(tenant('sunrise'), userIn('sunrise', 'owner@sunrise.test'))->two_factor_secret);

    post(tenantUrl('sunrise', '/user/confirmed-two-factor-authentication'), ['code' => '000000'])
        ->assertSessionHasErrors('code', null, 'confirmTwoFactorAuthentication');

    post(tenantUrl('sunrise', '/user/confirmed-two-factor-authentication'), ['code' => totp($secret)])
        ->assertRedirect()->assertSessionHas('status', 'two-factor-authentication-confirmed');

    get(tenantUrl('sunrise', '/iam/profile'))->assertOk()->assertSeeHtml('data-recovery-codes');
    expect(freshUser(tenant('sunrise'), userIn('sunrise', 'owner@sunrise.test'))->hasTwoFactorEnabled())->toBeTrue();
});

it('challenges for a code at sign-in and accepts a valid one', function (): void {
    $secret = enableTwoFactor(tenant('sunrise'), userIn('sunrise', 'owner@sunrise.test'));
    post(tenantUrl('sunrise', '/logout'));

    post(tenantUrl('sunrise', '/login'), ['email' => 'owner@sunrise.test', 'password' => 'Password123'])
        ->assertRedirect(tenantUrl('sunrise', '/two-factor-challenge'));
    assertGuest();
    get(tenantUrl('sunrise', '/two-factor-challenge'))->assertOk();

    post(tenantUrl('sunrise', '/two-factor-challenge'), ['code' => '123456'])->assertSessionHasErrors('code');
    assertGuest();

    post(tenantUrl('sunrise', '/login'), ['email' => 'owner@sunrise.test', 'password' => 'Password123']);
    post(tenantUrl('sunrise', '/two-factor-challenge'), ['code' => totp($secret, 1)])->assertRedirect(tenantUrl('sunrise', '/dashboard'));
    assertAuthenticatedAs(userIn('sunrise', 'owner@sunrise.test'));
});

it('accepts each recovery code only once', function (): void {
    enableTwoFactor(tenant('sunrise'), userIn('sunrise', 'owner@sunrise.test'));
    post(tenantUrl('sunrise', '/logout'));
    $code = freshUser(tenant('sunrise'), userIn('sunrise', 'owner@sunrise.test'))->recoveryCodes()[0];

    post(tenantUrl('sunrise', '/login'), ['email' => 'owner@sunrise.test', 'password' => 'Password123']);
    post(tenantUrl('sunrise', '/two-factor-challenge'), ['recovery_code' => $code])->assertRedirect(tenantUrl('sunrise', '/dashboard'));
    assertAuthenticatedAs(userIn('sunrise', 'owner@sunrise.test'));
    post(tenantUrl('sunrise', '/logout'));

    post(tenantUrl('sunrise', '/login'), ['email' => 'owner@sunrise.test', 'password' => 'Password123']);
    post(tenantUrl('sunrise', '/two-factor-challenge'), ['recovery_code' => $code])->assertSessionHasErrors('recovery_code');
    assertGuest();
});

it('regenerates recovery codes and turns two-factor authentication off', function (): void {
    enableTwoFactor(tenant('sunrise'), userIn('sunrise', 'owner@sunrise.test'));
    $before = freshUser(tenant('sunrise'), userIn('sunrise', 'owner@sunrise.test'))->recoveryCodes();

    post(tenantUrl('sunrise', '/user/two-factor-recovery-codes'))->assertSessionHas('status', 'recovery-codes-generated');
    expect(freshUser(tenant('sunrise'), userIn('sunrise', 'owner@sunrise.test'))->recoveryCodes())->not->toBe($before);

    delete(tenantUrl('sunrise', '/user/two-factor-authentication'))->assertSessionHas('status', 'two-factor-authentication-disabled');
    expect(freshUser(tenant('sunrise'), userIn('sunrise', 'owner@sunrise.test'))->hasTwoFactorEnabled())->toBeFalse();
});
