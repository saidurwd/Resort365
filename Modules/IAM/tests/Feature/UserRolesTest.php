<?php

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\IAM\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patch;
use function Pest\Laravel\put;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise']));
    withDefaultRoles(Tenant::factory()->create(['slug' => 'greenvalley']));
    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::TenantOwner, ['email' => 'owner@sunrise.test']));
});

/**
 * @return list<string>
 */
function rolesOf(User $user): array
{
    return app(TenantContext::class)->run(tenant('sunrise'), fn (): array => ($user->fresh() ?? throw new RuntimeException('missing'))->getRoleNames()->sort()->values()->all());
}

it('assigns roles to a user', function (): void {
    $user = tenantUser(tenant('sunrise'));

    get(tenantUrl('sunrise', '/iam/users/'.$user->id.'/edit'))->assertOk();
    put(tenantUrl('sunrise', '/iam/users/'.$user->id.'/roles'), ['roles' => [
        roleId(tenant('sunrise'), DefaultRole::FrontDeskAgent),
        roleId(tenant('sunrise'), DefaultRole::ReservationAgent),
    ]])->assertSessionHasNoErrors();

    expect(rolesOf($user))->toBe(['front-desk-agent', 'reservation-agent']);
});

it('rejects roles of another tenant and an empty role list', function (): void {
    $user = tenantUser(tenant('sunrise'));

    put(tenantUrl('sunrise', '/iam/users/'.$user->id.'/roles'), ['roles' => [roleId(tenant('greenvalley'), DefaultRole::TenantOwner)]])->assertSessionHasErrors('roles.0');
    put(tenantUrl('sunrise', '/iam/users/'.$user->id.'/roles'), ['roles' => []])->assertSessionHasErrors('roles');
});

it('keeps at least one active Tenant Owner', function (): void {
    $owner = userIn('sunrise', 'owner@sunrise.test');

    put(tenantUrl('sunrise', '/iam/users/'.$owner->id.'/roles'), ['roles' => [roleId(tenant('sunrise'), DefaultRole::Auditor)]])->assertSessionHasErrors('roles');
    expect(rolesOf($owner))->toBe(['tenant-owner']);

    // With a second owner, the first may be deactivated...
    $second = tenantUserAs(tenant('sunrise'), DefaultRole::TenantOwner);
    actingAs($second);
    patch(tenantUrl('sunrise', '/iam/users/'.$owner->id.'/deactivate'))->assertSessionHasNoErrors();

    // ...but then the second is the last active owner, and a manager cannot deactivate them.
    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::GeneralManager));
    patch(tenantUrl('sunrise', '/iam/users/'.$second->id.'/deactivate'))->assertSessionHasErrors('user');
});

it('shows each user\'s roles in the list', function (): void {
    tenantUserAs(tenant('sunrise'), DefaultRole::Accountant, ['name' => 'Farzana Akter']);

    $rows = (array) getJson(tenantUrl('sunrise', '/iam/users/data?draw=1&start=0&length=50'))->json('data');
    $farzana = collect($rows)->firstWhere('name', 'Farzana Akter');

    expect($farzana['roles'] ?? '')->toContain(__('Accountant'));
});
