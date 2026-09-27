<?php

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Modules\IAM\Enums\UserStatus;
use Modules\IAM\Models\User;
use Modules\IAM\Notifications\UserInvitation;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\travel;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();
    $sunrise = withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise', 'name' => 'Sunrise Resorts Ltd']));
    withDefaultRoles(Tenant::factory()->create(['slug' => 'greenvalley']));
    tenantUserAs($sunrise, DefaultRole::TenantOwner, ['name' => 'Rahim Uddin', 'email' => 'owner@sunrise.test']);
});

function invitee(Tenant $tenant, string $email): ?User
{
    return app(TenantContext::class)->run($tenant, fn (): ?User => User::query()->where('email', $email)->first());
}

function invitationUrl(User $invitee): string
{
    $url = null;

    Notification::assertSentTo($invitee, UserInvitation::class, function (UserInvitation $notification) use (&$url): bool {
        $url = $notification->url;

        return true;
    });

    return (string) $url;
}

it('invites a user by email', function (): void {
    actingAs(userIn('sunrise', 'owner@sunrise.test'));

    post(tenantUrl('sunrise', '/iam/users'), ['name' => 'Nusrat Jahan', 'email' => 'Nusrat@Sunrise.test', 'roles' => [roleId(tenant('sunrise'), DefaultRole::FrontDeskAgent)]])
        ->assertRedirect(tenantUrl('sunrise', '/iam/users'))
        ->assertSessionHas('success');

    $invitee = invitee(tenant('sunrise'), 'nusrat@sunrise.test');

    expect($invitee?->status)->toBe(UserStatus::Invited)
        ->and($invitee?->password)->toBeNull()
        ->and($invitee?->invited_by)->toBe(userIn('sunrise', 'owner@sunrise.test')->id);

    $url = invitationUrl($invitee);
    expect($url)->toStartWith(tenantUrl('sunrise', '/iam/invitations/'))->toContain('signature=');

    Notification::assertSentTo($invitee, UserInvitation::class, fn (UserInvitation $notification): bool => $notification->tenantName === 'Sunrise Resorts Ltd'
        && $notification->inviterName === 'Rahim Uddin'
        && str_contains((string) $notification->toMail($invitee)->subject, 'Sunrise Resorts Ltd'));
});

it('rejects an email already used in the same tenant but allows it in another', function (): void {
    actingAs(userIn('sunrise', 'owner@sunrise.test'));

    post(tenantUrl('sunrise', '/iam/users'), ['name' => 'Again', 'email' => 'owner@sunrise.test', 'roles' => [roleId(tenant('sunrise'), DefaultRole::FrontDeskAgent)]])->assertSessionHasErrors('email');

    actingAs(tenantUserAs(tenant('greenvalley'), DefaultRole::TenantOwner));
    post(tenantUrl('greenvalley', '/iam/users'), ['name' => 'Rahim at Valley', 'email' => 'owner@sunrise.test', 'roles' => [roleId(tenant('greenvalley'), DefaultRole::Auditor)]])->assertSessionHasNoErrors();

    expect(invitee(tenant('greenvalley'), 'owner@sunrise.test')?->status)->toBe(UserStatus::Invited);
});

it('validates the invitation form', function (): void {
    actingAs(userIn('sunrise', 'owner@sunrise.test'));

    post(tenantUrl('sunrise', '/iam/users'), ['name' => '', 'email' => 'not-an-email'])->assertSessionHasErrors(['name', 'email', 'roles']);
});

it('lets the invitee set a password, activates the account and signs them in', function (): void {
    actingAs(userIn('sunrise', 'owner@sunrise.test'));
    post(tenantUrl('sunrise', '/iam/users'), ['name' => 'Nusrat Jahan', 'email' => 'nusrat@sunrise.test', 'roles' => [roleId(tenant('sunrise'), DefaultRole::FrontDeskAgent)]]);
    post(tenantUrl('sunrise', '/logout'));
    $url = invitationUrl(invitee(tenant('sunrise'), 'nusrat@sunrise.test') ?? throw new RuntimeException('missing'));

    get($url)->assertOk()->assertSee('nusrat@sunrise.test')->assertSee('Join Sunrise Resorts Ltd');

    post($url, ['name' => 'Nusrat J.', 'password' => 'weak', 'password_confirmation' => 'weak'])->assertSessionHasErrors('password');

    post($url, ['name' => 'Nusrat J.', 'password' => 'Welcome123', 'password_confirmation' => 'Welcome123'])
        ->assertRedirect(tenantUrl('sunrise', '/dashboard'));

    $user = userIn('sunrise', 'nusrat@sunrise.test');
    assertAuthenticatedAs($user);
    expect($user->status)->toBe(UserStatus::Active)
        ->and($user->name)->toBe('Nusrat J.')
        ->and($user->hasVerifiedEmail())->toBeTrue();
});

it('does not reuse an accepted invitation', function (): void {
    actingAs(userIn('sunrise', 'owner@sunrise.test'));
    post(tenantUrl('sunrise', '/iam/users'), ['name' => 'Nusrat', 'email' => 'nusrat@sunrise.test', 'roles' => [roleId(tenant('sunrise'), DefaultRole::FrontDeskAgent)]]);
    post(tenantUrl('sunrise', '/logout'));
    $url = invitationUrl(invitee(tenant('sunrise'), 'nusrat@sunrise.test') ?? throw new RuntimeException('missing'));

    post($url, ['name' => 'Nusrat', 'password' => 'Welcome123', 'password_confirmation' => 'Welcome123']);
    post(tenantUrl('sunrise', '/logout'));

    get($url)->assertRedirect(tenantUrl('sunrise', '/login'))->assertSessionHas('info');
    post($url, ['name' => 'Attacker', 'password' => 'Takeover123', 'password_confirmation' => 'Takeover123'])->assertSessionHasErrors('password');
});

it('rejects tampered and expired invitation links', function (): void {
    $invitee = tenantUser(tenant('sunrise'), ['email' => 'new@sunrise.test'], fn ($factory) => $factory->invited());

    get(tenantUrl('sunrise', '/iam/invitations/'.$invitee->id))->assertForbidden();

    $url = URL::temporarySignedRoute('iam.invitations.show', now()->addDay(), ['tenant' => 'sunrise', 'user' => $invitee->id]);
    get($url.'x')->assertForbidden();

    travel(2)->days();
    $expired = URL::temporarySignedRoute('iam.invitations.show', now()->subMinute(), ['tenant' => 'sunrise', 'user' => $invitee->id]);
    get($expired)->assertForbidden();
});

it('does not open another tenant\'s invitation', function (): void {
    $invitee = tenantUser(tenant('sunrise'), ['email' => 'new@sunrise.test'], fn ($factory) => $factory->invited());
    $url = URL::temporarySignedRoute('iam.invitations.show', now()->addDay(), ['tenant' => 'greenvalley', 'user' => $invitee->id]);

    get($url)->assertNotFound();
});

it('resends an invitation', function (): void {
    actingAs(userIn('sunrise', 'owner@sunrise.test'));
    $invitee = tenantUser(tenant('sunrise'), ['email' => 'new@sunrise.test'], fn ($factory) => $factory->invited());

    post(tenantUrl('sunrise', '/iam/users/'.$invitee->id.'/invitation'))->assertSessionHas('success');

    Notification::assertSentTo($invitee, UserInvitation::class);
});
