<?php

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Modules\IAM\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\put;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    actingAs(tenantUser(Tenant::factory()->create(['slug' => 'sunrise']), ['name' => 'Rahim Uddin', 'email' => 'owner@sunrise.test']));
});

function reloaded(Tenant $tenant, User $user): User
{
    return app(TenantContext::class)->run($tenant, fn (): User => $user->fresh() ?? throw new RuntimeException('missing'));
}

it('shows the profile page', function (): void {
    get(tenantUrl('sunrise', '/iam/profile'))->assertOk()
        ->assertSee('Rahim Uddin')
        ->assertSee(__('Two-factor authentication'))
        ->assertSee(__('Recent sign-in activity'));
});

it('updates the name', function (): void {
    put(tenantUrl('sunrise', '/user/profile-information'), ['name' => 'Rahim U.', 'email' => 'owner@sunrise.test'])
        ->assertSessionHasNoErrors()->assertSessionHas('status', 'profile-information-updated');

    expect(reloaded(tenant('sunrise'), userIn('sunrise', 'owner@sunrise.test'))->name)->toBe('Rahim U.');
});

it('asks to verify a changed email', function (): void {
    Notification::fake();

    put(tenantUrl('sunrise', '/user/profile-information'), ['name' => 'Rahim Uddin', 'email' => 'rahim@sunrise.test'])->assertSessionHasNoErrors();

    $user = userIn('sunrise', 'rahim@sunrise.test');
    expect($user->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('refuses an email used by another user of the tenant', function (): void {
    tenantUser(tenant('sunrise'), ['email' => 'taken@sunrise.test']);

    put(tenantUrl('sunrise', '/user/profile-information'), ['name' => 'Rahim', 'email' => 'taken@sunrise.test'])
        ->assertSessionHasErrors('email', null, 'updateProfileInformation');
});

it('changes the password with the current one and the policy', function (): void {
    put(tenantUrl('sunrise', '/user/password'), ['current_password' => 'wrong', 'password' => 'NewSecret123', 'password_confirmation' => 'NewSecret123'])
        ->assertSessionHasErrors('current_password', null, 'updatePassword');
    put(tenantUrl('sunrise', '/user/password'), ['current_password' => 'Password123', 'password' => 'alllowercase', 'password_confirmation' => 'alllowercase'])
        ->assertSessionHasErrors('password', null, 'updatePassword');

    put(tenantUrl('sunrise', '/user/password'), ['current_password' => 'Password123', 'password' => 'NewSecret123', 'password_confirmation' => 'NewSecret123'])
        ->assertSessionHasNoErrors();

    expect(Hash::check('NewSecret123', (string) reloaded(tenant('sunrise'), userIn('sunrise', 'owner@sunrise.test'))->password))->toBeTrue();
});

it('saves language and colour mode', function (): void {
    patch(tenantUrl('sunrise', '/iam/profile/preferences'), ['locale' => 'en', 'theme' => 'dark'])->assertSessionHas('success');

    expect(reloaded(tenant('sunrise'), userIn('sunrise', 'owner@sunrise.test'))->theme)->toBe('dark');

    patch(tenantUrl('sunrise', '/iam/profile/preferences'), ['theme' => 'purple'])->assertSessionHasErrors('theme');
    patch(tenantUrl('sunrise', '/iam/profile/preferences'), ['locale' => 'xx'])->assertSessionHasErrors('locale');
});

it('saves the colour mode from the navbar toggle and applies it on the next page', function (): void {
    patchJson(tenantUrl('sunrise', '/iam/profile/preferences'), ['theme' => 'light'])->assertOk()->assertJson(['theme' => 'light']);

    get(tenantUrl('sunrise', '/dashboard'))->assertOk()->assertSeeHtml('let theme = "light"')->assertSeeHtml('<meta name="theme-save-url"')
        ->assertSee(__('Sign out'));
});
