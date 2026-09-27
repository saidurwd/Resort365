<?php

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\IAM\Enums\UserStatus;
use Modules\IAM\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patch;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $sunrise = Tenant::factory()->create(['slug' => 'sunrise']);
    Tenant::factory()->create(['slug' => 'greenvalley']);
    tenantUser($sunrise, ['name' => 'Rahim Uddin', 'email' => 'owner@sunrise.test']);
});

function statusOf(Tenant $tenant, User $user): UserStatus
{
    return app(TenantContext::class)->run($tenant, fn (): UserStatus => ($user->fresh() ?? throw new RuntimeException('missing'))->status);
}

it('shows the users screen', function (): void {
    actingAs(userIn('sunrise', 'owner@sunrise.test'));

    get(tenantUrl('sunrise', '/iam/users'))->assertOk()->assertSee('users-table')->assertSeeHtml('id="invite-user"');
});

it('lists only the current tenant\'s users', function (): void {
    tenantUser(tenant('sunrise'), ['name' => 'Nusrat Jahan']);
    tenantUser(tenant('greenvalley'), ['name' => 'Tanvir Ahmed']);
    actingAs(userIn('sunrise', 'owner@sunrise.test'));

    $names = array_column((array) getJson(tenantUrl('sunrise', '/iam/users/data?draw=1&start=0&length=50'))->assertOk()->json('data'), 'name');

    expect($names)->toEqualCanonicalizing(['Rahim Uddin', 'Nusrat Jahan']);
});

it('deactivates and reactivates a user', function (): void {
    $user = tenantUser(tenant('sunrise'));
    actingAs(userIn('sunrise', 'owner@sunrise.test'));

    patch(tenantUrl('sunrise', '/iam/users/'.$user->id.'/deactivate'))->assertSessionHas('success');
    expect(statusOf(tenant('sunrise'), $user))->toBe(UserStatus::Inactive);

    patch(tenantUrl('sunrise', '/iam/users/'.$user->id.'/activate'))->assertSessionHas('success');
    expect(statusOf(tenant('sunrise'), $user))->toBe(UserStatus::Active);
});

it('puts a never-accepted user back to invited on reactivation', function (): void {
    $user = tenantUser(tenant('sunrise'), [], fn ($factory) => $factory->invited()->state(['status' => UserStatus::Inactive]));
    actingAs(userIn('sunrise', 'owner@sunrise.test'));

    patch(tenantUrl('sunrise', '/iam/users/'.$user->id.'/activate'));

    expect(statusOf(tenant('sunrise'), $user))->toBe(UserStatus::Invited);
});

it('does not let users deactivate themselves', function (): void {
    actingAs(userIn('sunrise', 'owner@sunrise.test'));

    patch(tenantUrl('sunrise', '/iam/users/'.userIn('sunrise', 'owner@sunrise.test')->id.'/deactivate'))->assertForbidden();
    expect(statusOf(tenant('sunrise'), userIn('sunrise', 'owner@sunrise.test')))->toBe(UserStatus::Active);
});

it('cannot act on another tenant\'s user', function (): void {
    $other = tenantUser(tenant('greenvalley'));
    actingAs(userIn('sunrise', 'owner@sunrise.test'));

    patch(tenantUrl('sunrise', '/iam/users/'.$other->id.'/deactivate'))->assertNotFound();
    expect(statusOf(tenant('greenvalley'), $other))->toBe(UserStatus::Active);
});
