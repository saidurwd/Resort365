<?php

/*
| Two-factor sign-in for platform admins (Phase 7 review): setting it up with an authenticator app, the second step after the
| password (an app code, or a recovery code used once), the requirement in production (also for Horizon and Pulse), and the reset
| from the command line.
*/
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Platform\Actions\StartPlatformTwoFactor;
use Modules\Platform\Models\PlatformAdmin;
use PragmaRX\Google2FA\Google2FA;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\travel;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    PlatformAdmin::factory()->create(['name' => 'Platform Admin', 'email' => 'admin@resort365.test']);
});

function tfAdmin(): PlatformAdmin
{
    return PlatformAdmin::query()->where('email', 'admin@resort365.test')->firstOrFail();
}

/** The code the authenticator app shows now for the admin's secret. */
function tfCode(PlatformAdmin $admin): string
{
    return app(Google2FA::class)->getCurrentOtp(decrypt((string) $admin->fresh()?->two_factor_secret));
}

/** An admin with two-factor sign-in on, as after setting it up. */
function tfEnabled(): PlatformAdmin
{
    $admin = app(StartPlatformTwoFactor::class)->handle(tfAdmin());
    $admin->forceFill(['two_factor_confirmed_at' => now()])->save();

    return $admin;
}

it('sets two-factor sign-in up with a code from the app, and shows the recovery codes once', function (): void {
    actingAs(tfAdmin(), 'platform');

    get(centralUrl('/platform/security/two-factor'))->assertOk()->assertSeeHtml('data-two-factor-setup')->assertSeeHtml('<svg');
    post(centralUrl('/platform/security/two-factor'), ['code' => '000000'])->assertSessionHasErrors('code');
    expect(tfAdmin()->hasEnabledTwoFactorAuthentication())->toBeFalse();

    post(centralUrl('/platform/security/two-factor'), ['code' => tfCode(tfAdmin())])->assertRedirect(centralUrl('/platform/security/two-factor'));

    expect(tfAdmin()->hasEnabledTwoFactorAuthentication())->toBeTrue()->and(tfAdmin()->recoveryCodes())->toHaveCount(8)
        ->and(DB::table('platform_audit_log')->where('action', 'admin.two_factor_enabled')->count())->toBe(1);
    // Shown on the page the confirmation leads to, and never again.
    get(centralUrl('/platform/security/two-factor'))->assertSeeHtml('data-two-factor-on')->assertSeeHtml('data-recovery-codes');
    get(centralUrl('/platform/security/two-factor'))->assertSeeHtml('data-two-factor-on')->assertDontSeeHtml('data-recovery-codes');
});

it('asks for the app\'s code after the password, and signs in only with it', function (): void {
    $admin = tfEnabled();

    post(centralUrl('/platform/login'), ['email' => 'admin@resort365.test', 'password' => 'Password123'])->assertRedirect(centralUrl('/platform/two-factor-challenge'));
    assertGuest('platform');
    get(centralUrl('/platform'))->assertRedirect(centralUrl('/platform/login'));
    get(centralUrl('/platform/two-factor-challenge'))->assertOk()->assertSee('authenticator app');

    post(centralUrl('/platform/two-factor-challenge'), ['code' => '000000'])->assertSessionHasErrors('code');
    assertGuest('platform');

    post(centralUrl('/platform/two-factor-challenge'), ['code' => tfCode($admin)])->assertRedirect(centralUrl('/platform'));
    assertAuthenticatedAs($admin->fresh(), 'platform');
    expect($admin->fresh()?->last_login_at)->not->toBeNull();
});

it('takes a recovery code once', function (): void {
    $admin = tfEnabled();
    $code = $admin->recoveryCodes()[0];

    post(centralUrl('/platform/login'), ['email' => 'admin@resort365.test', 'password' => 'Password123']);
    post(centralUrl('/platform/two-factor-challenge'), ['recovery_code' => $code])->assertRedirect(centralUrl('/platform'));

    expect(tfAdmin()->recoveryCodes())->not->toContain($code)->toHaveCount(8);

    post(centralUrl('/platform/logout'));
    post(centralUrl('/platform/login'), ['email' => 'admin@resort365.test', 'password' => 'Password123']);
    post(centralUrl('/platform/two-factor-challenge'), ['recovery_code' => $code])->assertSessionHasErrors('code');
    assertGuest('platform');
});

it('forgets a password step after five minutes, and goes back to sign in without one', function (): void {
    tfEnabled();

    get(centralUrl('/platform/two-factor-challenge'))->assertRedirect(centralUrl('/platform/login'));

    post(centralUrl('/platform/login'), ['email' => 'admin@resort365.test', 'password' => 'Password123']);
    travel(6)->minutes();
    post(centralUrl('/platform/two-factor-challenge'), ['code' => tfCode(tfAdmin())])->assertRedirect(centralUrl('/platform/login'));
    assertGuest('platform');
});

it('requires it where configured (production by default), also for Horizon and Pulse', function (): void {
    actingAs(tfAdmin(), 'platform');

    get(centralUrl('/platform'))->assertOk();

    config(['platform.require_two_factor' => true]);
    get(centralUrl('/platform'))->assertRedirect(centralUrl('/platform/security/two-factor'));
    get(centralUrl('/platform/tenants'))->assertRedirect(centralUrl('/platform/security/two-factor'));
    get(centralUrl('/platform/horizon'))->assertRedirect(centralUrl('/platform/security/two-factor'));
    get(centralUrl('/platform/security/two-factor'))->assertOk()->assertSee('must sign in with a second factor');

    actingAs(tfEnabled(), 'platform');
    get(centralUrl('/platform'))->assertOk();
});

it('resets an admin\'s two-factor sign-in from the command line', function (): void {
    tfEnabled();

    artisan('platform:reset-two-factor', ['email' => 'nobody@example.test'])->assertFailed();
    artisan('platform:reset-two-factor', ['email' => 'Admin@Resort365.test'])->assertSuccessful();

    expect(tfAdmin()->hasEnabledTwoFactorAuthentication())->toBeFalse()->and(tfAdmin()->two_factor_secret)->toBeNull()
        ->and(DB::table('platform_audit_log')->where('action', 'admin.two_factor_reset')->count())->toBe(1);
    post(centralUrl('/platform/login'), ['email' => 'admin@resort365.test', 'password' => 'Password123'])->assertRedirect(centralUrl('/platform'));
});

it('never shows the secret or the recovery codes in JSON', function (): void {
    $keys = array_keys(tfEnabled()->toArray());

    expect(in_array('two_factor_secret', $keys, true))->toBeFalse()->and(in_array('two_factor_recovery_codes', $keys, true))->toBeFalse();
});
