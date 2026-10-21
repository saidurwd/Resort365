<?php

/*
| Platform console operations (Step 7.3): suspending and reactivating a tenant, signing in as one of its users (once, audited, tagged
| in the tenant's own audit trail) and announcements to the tenants.
*/
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Announcements;
use Modules\Core\DTOs\AnnouncementSummary;
use Modules\Platform\Actions\ReactivateTenant;
use Modules\Platform\Actions\SaveAnnouncement;
use Modules\Platform\Actions\StartImpersonation;
use Modules\Platform\Actions\SuspendTenant;
use Modules\Platform\Exceptions\PlatformRuleViolated;
use Modules\Platform\Models\Announcement;
use Modules\Platform\Models\ImpersonationSession;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticated;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\travelBack;
use function Pest\Laravel\travelTo;
use function Pest\Laravel\withSession;

require_once __DIR__.'/../Support/platform-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    pfSetup();
});

/** The one-time token at the end of an impersonation link. */
function coToken(string $url): string
{
    return basename($url);
}

it('suspends a tenant with a reason, and reactivates it', function (): void {
    $admin = pfAdmin();
    $tenant = tenant('sunrise');

    expect(fn () => app(SuspendTenant::class)->handle($tenant, ' ', $admin))->toThrow(PlatformRuleViolated::class, 'Say why');
    app(SuspendTenant::class)->handle($tenant, 'Unpaid invoice', $admin);

    expect($tenant->fresh()->status)->toBe(TenantStatus::Suspended)->and(fn () => app(SuspendTenant::class)->handle($tenant->fresh(), 'again', $admin))->toThrow(PlatformRuleViolated::class, 'cannot be suspended');
    get(tenantUrl('sunrise', '/'))->assertForbidden();

    app(ReactivateTenant::class)->handle($tenant->fresh(), $admin);

    expect($tenant->fresh()->status)->toBe(TenantStatus::Active)->and(fn () => app(ReactivateTenant::class)->handle($tenant->fresh(), $admin))->toThrow(PlatformRuleViolated::class)
        ->and(pfAudit('tenant.'))->toBe(['tenant.suspended', 'tenant.reactivated']);
});

it('signs a platform admin in as a user once, shows the banner and ends the session on leaving', function (): void {
    $admin = pfAdmin();
    $user = tenantUserAs(tenant('sunrise'), DefaultRole::TenantOwner);

    expect(fn () => app(StartImpersonation::class)->handle($admin, tenant('sunrise'), $user->id, ' '))->toThrow(PlatformRuleViolated::class, 'Say why');
    $url = app(StartImpersonation::class)->handle($admin, tenant('sunrise'), $user->id, 'Support ticket 42');

    expect($url)->toStartWith(tenantUrl('sunrise', '/iam/impersonate/'));
    auth('platform')->logout();
    get($url)->assertRedirect()->assertSessionHas('impersonation.admin_name', 'Platform Admin');
    assertAuthenticated('web');
    get($url)->assertForbidden();

    $session = ImpersonationSession::query()->firstOrFail();
    expect($session->started_at)->not->toBeNull()->and($session->ended_at)->toBeNull()->and(pfAudit('impersonation.'))->toBe(['impersonation.requested', 'impersonation.started']);

    $state = ['session_id' => $session->id, 'admin_id' => $admin->id, 'admin_name' => 'Platform Admin', 'user_name' => $user->name];
    actingAs($user);
    withSession(['impersonation' => $state]);
    get(tenantUrl('sunrise', '/dashboard'))->assertOk()->assertSee('Platform Admin');
    post(tenantUrl('sunrise', '/iam/impersonation/leave'))->assertRedirect(centralUrl('/platform'));

    expect($session->fresh()->ended_at)->not->toBeNull()->and(pfAudit('impersonation.'))->toContain('impersonation.ended');
});

it('tags audit entries made while signed in as someone', function (): void {
    $admin = pfAdmin();
    $user = tenantUserAs(tenant('sunrise'), DefaultRole::TenantOwner);
    $store = app('session.store');
    $store->put('impersonation', ['session_id' => 1, 'admin_id' => $admin->id, 'admin_name' => 'Platform Admin', 'user_name' => $user->name]);
    request()->setLaravelSession($store);

    booking(fn () => activity()->performedOn($user)->log('looked'));

    $row = DB::table('activity_log')->where('description', 'looked')->first();
    expect(json_decode((string) $row->properties, true)['impersonated_by'] ?? null)->toMatchArray(['id' => $admin->id, 'name' => 'Platform Admin']);
});

it('refuses a link that expired, was made for another tenant, or has a bad token', function (): void {
    $admin = pfAdmin();
    $user = tenantUserAs(tenant('sunrise'), DefaultRole::TenantOwner);
    $url = app(StartImpersonation::class)->handle($admin, tenant('sunrise'), $user->id, 'Support');
    auth('platform')->logout();

    travelTo(now()->addMinutes(2));
    get($url)->assertForbidden();
    travelBack();
    get(tenantUrl('sunrise', '/iam/impersonate/not-a-token'))->assertForbidden();

    $other = withDefaultRoles(Tenant::factory()->create(['slug' => 'other']));
    get(tenantUrl('other', '/iam/impersonate/'.coToken($url)))->assertForbidden();
    expect($other->slug)->toBe('other');
});

it('will not sign in as an inactive user, a stranger, or at a suspended tenant', function (): void {
    $admin = pfAdmin();
    $user = tenantUserAs(tenant('sunrise'), DefaultRole::TenantOwner);
    $off = tenantUser(tenant('sunrise'), ['email' => 'off@sunrise.test']);
    booking(fn (): bool => $off->forceFill(['status' => 'inactive'])->save());

    expect(fn () => app(StartImpersonation::class)->handle($admin, tenant('sunrise'), $off->id, 'x'))->toThrow(PlatformRuleViolated::class, 'not an active user')
        ->and(fn () => app(StartImpersonation::class)->handle($admin, tenant('sunrise'), 999999, 'x'))->toThrow(PlatformRuleViolated::class, 'not an active user');

    app(SuspendTenant::class)->handle(tenant('sunrise'), 'Unpaid', $admin);

    expect(fn () => app(StartImpersonation::class)->handle($admin, tenant('sunrise')->fresh(), $user->id, 'x'))->toThrow(PlatformRuleViolated::class, 'suspended');
});

it('shows an announcement to the audience it is meant for, within its dates, most urgent first', function (): void {
    $starter = pfPlan('starter');
    $other = pfPlan('professional');
    $save = fn (array $overrides) => app(SaveAnnouncement::class)->handle(null, ['title' => 'T', 'body' => 'B', 'level' => 'info', 'audience' => 'all', ...$overrides]);
    $save(['title' => 'For all']);
    $save(['title' => 'Urgent', 'level' => 'danger']);
    $save(['title' => 'Starter only', 'audience' => 'plans', 'plan_ids' => [$starter->id]]);
    $save(['title' => 'Pro only', 'audience' => 'plans', 'plan_ids' => [$other->id]]);
    $save(['title' => 'This tenant', 'audience' => 'tenants', 'tenant_ids' => [tenant('sunrise')->id]]);
    $save(['title' => 'Later', 'starts_at' => now()->addDay()->toDateTimeString()]);
    $save(['title' => 'Over', 'starts_at' => now()->subDays(3)->toDateTimeString(), 'ends_at' => now()->subDay()->toDateTimeString()]);
    $titles = fn (): array => booking(fn (): array => array_map(fn (AnnouncementSummary $announcement) => $announcement->title, app(Announcements::class)->active()));

    expect($titles())->toBe(['Urgent', 'This tenant', 'For all']);

    pfMove('starter');

    expect($titles())->toBe(['Urgent', 'This tenant', 'Starter only', 'For all']);

    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::TenantOwner));
    get(tenantUrl('sunrise', '/dashboard'))->assertOk()->assertSee('Urgent')->assertSee('Starter only')->assertDontSee('Pro only')->assertDontSee('Later');
});

it('refuses an announcement for plans or tenants without any chosen, or one that ends before it starts', function (): void {
    $save = fn (array $overrides) => fn () => app(SaveAnnouncement::class)->handle(null, ['title' => 'T', 'body' => 'B', 'level' => 'info', 'audience' => 'all', ...$overrides]);

    expect($save(['audience' => 'plans']))->toThrow(PlatformRuleViolated::class, 'Choose the plans')->and($save(['audience' => 'tenants', 'tenant_ids' => [999999]]))->toThrow(PlatformRuleViolated::class, 'Choose the tenants')
        ->and($save(['starts_at' => '2030-01-02 00:00:00', 'ends_at' => '2030-01-01 00:00:00']))->toThrow(PlatformRuleViolated::class, 'ends after it starts');
    expect(Announcement::query()->count())->toBe(0);
});
