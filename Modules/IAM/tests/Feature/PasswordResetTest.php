<?php

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Modules\IAM\Models\User;

use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();
    Tenant::factory()->create(['slug' => 'sunrise']);
    Tenant::factory()->create(['slug' => 'greenvalley']);
});

function resetTokenFor(User $user): string
{
    $token = null;

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token, $user): bool {
        $token = $notification->token;

        return str_starts_with($notification->toMail($user)->actionUrl, tenantUrl(Tenant::query()->findOrFail($user->tenant_id)->slug, '/reset-password/'))
            && str_contains($notification->toMail($user)->actionUrl, 'email='.urlencode($user->email));
    });

    return (string) $token;
}

it('emails a reset link on the tenant subdomain with the plain email', function (): void {
    $user = tenantUser(tenant('sunrise'), ['email' => 'owner@sunrise.test']);

    get(tenantUrl('sunrise', '/forgot-password'))->assertOk();
    post(tenantUrl('sunrise', '/forgot-password'), ['email' => 'owner@sunrise.test'])->assertSessionHas('status');

    expect(resetTokenFor($user))->not->toBe('');
});

it('resets the password with a valid token', function (): void {
    $user = tenantUser(tenant('sunrise'), ['email' => 'owner@sunrise.test']);
    post(tenantUrl('sunrise', '/forgot-password'), ['email' => 'owner@sunrise.test']);
    $token = resetTokenFor($user);

    get(tenantUrl('sunrise', '/reset-password/'.$token.'?email=owner%40sunrise.test'))->assertOk()->assertSee('owner@sunrise.test');

    post(tenantUrl('sunrise', '/reset-password'), [
        'token' => $token, 'email' => 'owner@sunrise.test', 'password' => 'NewSecret123', 'password_confirmation' => 'NewSecret123',
    ])->assertSessionHasNoErrors()->assertRedirect(tenantUrl('sunrise', '/login'));

    post(tenantUrl('sunrise', '/login'), ['email' => 'owner@sunrise.test', 'password' => 'NewSecret123']);
    assertAuthenticatedAs($user);
});

it('never accepts a token from another tenant for the same email', function (): void {
    $inSunrise = tenantUser(tenant('sunrise'), ['email' => 'shared@example.com']);
    tenantUser(tenant('greenvalley'), ['email' => 'shared@example.com']);

    post(tenantUrl('sunrise', '/forgot-password'), ['email' => 'shared@example.com']);
    $sunriseToken = resetTokenFor($inSunrise);

    post(tenantUrl('greenvalley', '/reset-password'), [
        'token' => $sunriseToken, 'email' => 'shared@example.com', 'password' => 'Hijacked123', 'password_confirmation' => 'Hijacked123',
    ])->assertSessionHasErrors('email');

    post(tenantUrl('greenvalley', '/login'), ['email' => 'shared@example.com', 'password' => 'Hijacked123'])->assertSessionHasErrors('email');
});

it('enforces the password policy', function (): void {
    $user = tenantUser(tenant('sunrise'), ['email' => 'owner@sunrise.test']);
    $token = app(TenantContext::class)->run(tenant('sunrise'), fn () => Password::broker()->createToken($user));

    post(tenantUrl('sunrise', '/reset-password'), [
        'token' => $token, 'email' => 'owner@sunrise.test', 'password' => 'short', 'password_confirmation' => 'short',
    ])->assertSessionHasErrors('password');
});
