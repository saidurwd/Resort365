<?php

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\IAM\Models\Role;
use Modules\IAM\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise']));
    withDefaultRoles(Tenant::factory()->create(['slug' => 'greenvalley']));
    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::TenantOwner, ['email' => 'owner@sunrise.test']));
});

function sunriseRole(string $name): Role
{
    return app(TenantContext::class)->run(tenant('sunrise'), fn (): Role => Role::query()->where('name', $name)->firstOrFail());
}

it('lists default and custom roles', function (): void {
    get(tenantUrl('sunrise', '/iam/roles'))->assertOk()
        ->assertSee(__('Front Desk Agent'))
        ->assertSee(__('Accountant'))
        ->assertSee(__('Default'));
});

it('creates a custom role with permissions grouped by module', function (): void {
    get(tenantUrl('sunrise', '/iam/roles/create'))->assertOk()->assertSeeHtml('data-permission-group="iam"');

    post(tenantUrl('sunrise', '/iam/roles'), ['name' => 'Night manager', 'description' => 'Runs the night shift', 'permissions' => ['iam.user.view', 'iam.role.view']])
        ->assertRedirect(tenantUrl('sunrise', '/iam/roles'));

    $role = sunriseRole('Night manager');
    expect($role->is_system)->toBeFalse()
        ->and($role->permissions()->pluck('name')->sort()->values()->all())->toBe(['iam.role.view', 'iam.user.view']);
});

it('validates role names per tenant and permission names', function (): void {
    post(tenantUrl('sunrise', '/iam/roles'), ['name' => 'tenant-owner'])->assertSessionHasErrors('name');
    post(tenantUrl('sunrise', '/iam/roles'), ['name' => 'Night manager', 'permissions' => ['made.up.permission']])->assertSessionHasErrors('permissions.0');

    app(TenantContext::class)->run(tenant('greenvalley'), fn () => Role::query()->create(['name' => 'Night manager', 'guard_name' => 'web']));
    post(tenantUrl('sunrise', '/iam/roles'), ['name' => 'Night manager'])->assertSessionHasNoErrors();
});

it('edits a custom role', function (): void {
    $role = app(TenantContext::class)->run(tenant('sunrise'), fn (): Role => Role::query()->create(['name' => 'Night manager', 'guard_name' => 'web']));

    get(tenantUrl('sunrise', '/iam/roles/'.$role->id.'/edit'))->assertOk();
    put(tenantUrl('sunrise', '/iam/roles/'.$role->id), ['name' => 'Night auditor', 'permissions' => ['iam.user.view']])->assertSessionHasNoErrors();

    expect(sunriseRole('Night auditor')->permissions()->pluck('name')->all())->toBe(['iam.user.view']);
});

it('shows default roles read-only and refuses to change or delete them', function (): void {
    $role = sunriseRole('front-desk-agent');

    get(tenantUrl('sunrise', '/iam/roles/'.$role->id))->assertOk()->assertSee(__('This is a default role'));
    get(tenantUrl('sunrise', '/iam/roles/'.$role->id.'/edit'))->assertForbidden();
    put(tenantUrl('sunrise', '/iam/roles/'.$role->id), ['name' => 'Hacked'])->assertForbidden();
    delete(tenantUrl('sunrise', '/iam/roles/'.$role->id))->assertForbidden();
});

it('deletes a custom role only when no user has it', function (): void {
    $role = app(TenantContext::class)->run(tenant('sunrise'), fn (): Role => Role::query()->create(['name' => 'Night manager', 'guard_name' => 'web']));
    $user = tenantUser(tenant('sunrise'));
    app(TenantContext::class)->run(tenant('sunrise'), fn (): User => $user->assignRole($role));

    delete(tenantUrl('sunrise', '/iam/roles/'.$role->id))->assertSessionHasErrors('role');

    app(TenantContext::class)->run(tenant('sunrise'), fn (): User => $user->removeRole($role));
    delete(tenantUrl('sunrise', '/iam/roles/'.$role->id))->assertSessionHasNoErrors();

    expect(app(TenantContext::class)->run(tenant('sunrise'), fn (): bool => Role::query()->where('name', 'Night manager')->exists()))->toBeFalse();
});

it('cannot open another tenant\'s role', function (): void {
    $other = app(TenantContext::class)->run(tenant('greenvalley'), fn (): Role => Role::query()->where('name', 'auditor')->firstOrFail());

    get(tenantUrl('sunrise', '/iam/roles/'.$other->id))->assertNotFound();
});
