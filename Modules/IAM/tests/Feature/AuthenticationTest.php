<?php

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Modules\IAM\Enums\LoginEvent;
use Modules\IAM\Models\LoginHistory;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticated;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Tenant::factory()->create(['slug' => 'sunrise', 'name' => 'Sunrise Resorts Ltd']);
    Tenant::factory()->create(['slug' => 'greenvalley', 'name' => 'Green Valley Resort']);
});

/**
 * @return list<LoginEvent>
 */
function loginEvents(Tenant $tenant): array
{
    return app(TenantContext::class)->run($tenant, fn (): array => LoginHistory::query()->orderBy('id')->pluck('event')->all());
}

it('shows the sign-in page with the tenant name', function (): void {
    get(tenantUrl('sunrise', '/login'))->assertOk()->assertSee('Sign in to Sunrise Resorts Ltd');
});

it('signs a user in on their own tenant subdomain', function (): void {
    $user = tenantUser(tenant('sunrise'), ['email' => 'owner@sunrise.test']);

    post(tenantUrl('sunrise', '/login'), ['email' => 'owner@sunrise.test', 'password' => 'Password123'])
        ->assertRedirect(tenantUrl('sunrise', '/dashboard'));

    assertAuthenticatedAs($user);
    get(tenantUrl('sunrise', '/dashboard'))->assertOk()->assertSeeHtml('signed in as '.$user->name);
});

it('refuses a user on another tenant\'s subdomain', function (): void {
    tenantUser(tenant('sunrise'), ['email' => 'owner@sunrise.test']);

    post(tenantUrl('greenvalley', '/login'), ['email' => 'owner@sunrise.test', 'password' => 'Password123'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);

    assertGuest();
});

it('keeps the same email in two tenants separate', function (): void {
    $inSunrise = tenantUser(tenant('sunrise'), ['email' => 'shared@example.com', 'password' => 'SunrisePass1']);
    tenantUser(tenant('greenvalley'), ['email' => 'shared@example.com', 'password' => 'ValleyPass1']);

    post(tenantUrl('sunrise', '/login'), ['email' => 'shared@example.com', 'password' => 'ValleyPass1'])->assertSessionHasErrors('email');
    assertGuest();

    post(tenantUrl('sunrise', '/login'), ['email' => 'Shared@Example.com', 'password' => 'SunrisePass1'])->assertRedirect();
    assertAuthenticatedAs($inSunrise);
});

it('rejects a wrong password', function (): void {
    tenantUser(tenant('sunrise'), ['email' => 'owner@sunrise.test']);

    post(tenantUrl('sunrise', '/login'), ['email' => 'owner@sunrise.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
    assertGuest();
});

it('refuses a deactivated user with a clear message', function (): void {
    tenantUser(tenant('sunrise'), ['email' => 'old@sunrise.test'], fn ($factory) => $factory->inactive());

    post(tenantUrl('sunrise', '/login'), ['email' => 'old@sunrise.test', 'password' => 'Password123'])
        ->assertSessionHasErrors(['email' => __('This account has been deactivated. Contact your administrator.')]);

    assertGuest();
});

it('refuses an invited user who has not accepted yet', function (): void {
    tenantUser(tenant('sunrise'), ['email' => 'new@sunrise.test'], fn ($factory) => $factory->invited());

    post(tenantUrl('sunrise', '/login'), ['email' => 'new@sunrise.test', 'password' => ''])->assertSessionHasErrors();
    assertGuest();
});

it('ends a session presented on another tenant\'s subdomain', function (): void {
    actingAs(tenantUser(tenant('sunrise')));

    get(tenantUrl('greenvalley', '/dashboard'))->assertRedirect(tenantUrl('greenvalley', '/login'));
    assertGuest();
});

it('signs out a user who is deactivated while signed in', function (): void {
    $user = tenantUser(tenant('sunrise'));
    actingAs($user);
    get(tenantUrl('sunrise', '/dashboard'))->assertOk();

    app(TenantContext::class)->run(tenant('sunrise'), fn () => $user->forceFill(['status' => 'inactive'])->save());

    get(tenantUrl('sunrise', '/dashboard'))
        ->assertRedirect(tenantUrl('sunrise', '/login'))
        ->assertSessionHas('error');
    assertGuest();
});

it('sends unverified users to the verification notice', function (): void {
    actingAs(tenantUser(tenant('sunrise'), [], fn ($factory) => $factory->unverified()));

    get(tenantUrl('sunrise', '/dashboard'))->assertRedirect(tenantUrl('sunrise', '/email/verify'));
    get(tenantUrl('sunrise', '/email/verify'))->assertOk()->assertSee(__('Resend verification email'));
});

it('redirects guests to the tenant sign-in page', function (): void {
    get(tenantUrl('sunrise', '/'))->assertRedirect(tenantUrl('sunrise', '/dashboard'));
    get(tenantUrl('sunrise', '/dashboard'))->assertRedirect(tenantUrl('sunrise', '/login'));
    get(tenantUrl('sunrise', '/iam/users'))->assertRedirect(tenantUrl('sunrise', '/login'));
});

it('locks out after five failed attempts and records it', function (): void {
    tenantUser(tenant('sunrise'), ['email' => 'owner@sunrise.test']);

    foreach (range(1, 5) as $attempt) {
        post(tenantUrl('sunrise', '/login'), ['email' => 'owner@sunrise.test', 'password' => 'wrong'.$attempt]);
    }

    post(tenantUrl('sunrise', '/login'), ['email' => 'owner@sunrise.test', 'password' => 'Password123'])
        ->assertSessionHasErrors(['email']);
    assertGuest();

    expect(loginEvents(tenant('sunrise')))->toContain(LoginEvent::Lockout)
        ->and(array_count_values(array_map(fn (LoginEvent $event): string => $event->value, loginEvents(tenant('sunrise'))))['failed'])->toBe(5);
});

it('keeps lockouts separate per tenant', function (): void {
    tenantUser(tenant('sunrise'), ['email' => 'shared@example.com']);
    $inValley = tenantUser(tenant('greenvalley'), ['email' => 'shared@example.com']);

    foreach (range(1, 6) as $attempt) {
        post(tenantUrl('sunrise', '/login'), ['email' => 'shared@example.com', 'password' => 'wrong']);
    }

    post(tenantUrl('greenvalley', '/login'), ['email' => 'shared@example.com', 'password' => 'Password123'])->assertRedirect();
    assertAuthenticatedAs($inValley);
});

it('records sign-in, failure and sign-out history and the last sign-in', function (): void {
    $user = tenantUser(tenant('sunrise'), ['email' => 'owner@sunrise.test']);

    post(tenantUrl('sunrise', '/login'), ['email' => 'owner@sunrise.test', 'password' => 'nope']);
    post(tenantUrl('sunrise', '/login'), ['email' => 'owner@sunrise.test', 'password' => 'Password123']);
    assertAuthenticated();
    post(tenantUrl('sunrise', '/logout'))->assertRedirect();

    expect(loginEvents(tenant('sunrise')))->toBe([LoginEvent::Failed, LoginEvent::Login, LoginEvent::Logout])
        ->and(loginEvents(tenant('greenvalley')))->toBe([]);

    app(TenantContext::class)->run(tenant('sunrise'), function () use ($user): void {
        expect($user->fresh()?->last_login_at)->not->toBeNull()
            ->and(LoginHistory::query()->where('event', 'login')->value('user_id'))->toBe($user->id);
    });
});

it('uses the user\'s language', function (): void {
    config(['app.available_locales' => ['en' => 'English', 'bn' => 'বাংলা']]);
    actingAs(tenantUser(tenant('sunrise'), ['locale' => 'bn']));

    Route::domain('{tenant}.'.config('tenancy.central_domain'))->middleware(['web', 'tenant', 'auth'])
        ->get('/_locale', fn (): string => app()->getLocale());

    get(tenantUrl('sunrise', '/_locale'))->assertOk()->assertSeeText('bn');
});
