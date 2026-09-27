<?php

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use App\Support\Authorization\PermissionDefinition;
use App\Support\Authorization\PermissionRegistry;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\IAM\Models\Permission;
use Modules\IAM\Models\Role;
use Modules\IAM\Models\User;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

/**
 * @return list<string>
 */
function rolePermissions(Tenant $tenant, string $role): array
{
    return app(TenantContext::class)->run($tenant, fn (): array => Role::findByName($role, 'web')->permissions()->pluck('name')->sort()->values()->all());
}

it('stores registered permissions and creates default roles for every tenant', function (): void {
    [$a, $b] = Tenant::factory()->count(2)->create()->all();

    artisan('permissions:sync')->assertSuccessful();
    artisan('permissions:sync')->assertSuccessful();

    expect(Permission::query()->pluck('name')->sort()->values()->all())->toBe(app(PermissionRegistry::class)->names());

    foreach ([$a, $b] as $tenant) {
        app(TenantContext::class)->run($tenant, function (): void {
            expect(Role::query()->where('is_system', true)->pluck('name')->sort()->values()->all())
                ->toBe(collect(DefaultRole::cases())->map->value->sort()->values()->all());
        });
    }

    expect(rolePermissions($a, 'tenant-owner'))->toBe(app(PermissionRegistry::class)->names())
        ->and(rolePermissions($a, 'auditor'))->toBe(['core.audit.view', 'core.sequence.view', 'core.setting.view', 'iam.role.view', 'iam.user.view'])
        ->and(rolePermissions($a, 'general-manager'))->toBe([
            'core.audit.view', 'core.sequence.update', 'core.sequence.view', 'core.setting.update', 'core.setting.view',
            'iam.role.view', 'iam.user.invite', 'iam.user.update', 'iam.user.view',
        ])
        ->and(rolePermissions($a, 'front-desk-agent'))->toBe([]);
});

it('updates default roles when a module adds a permission, and leaves custom roles alone', function (): void {
    $tenant = Tenant::factory()->create();
    artisan('permissions:sync');
    app(TenantContext::class)->run($tenant, fn () => Role::query()->create(['name' => 'Night manager', 'guard_name' => 'web'])->givePermissionTo('iam.user.view'));

    app(PermissionRegistry::class)->register('Front office', [
        new PermissionDefinition('frontoffice.desk.view', 'View front desk', [DefaultRole::FrontDeskAgent]),
    ]);
    artisan('permissions:sync')->assertSuccessful();

    expect(rolePermissions($tenant, 'front-desk-agent'))->toBe(['frontoffice.desk.view'])
        ->and(rolePermissions($tenant, 'Night manager'))->toBe(['iam.user.view']);
});

it('prunes permissions no module registers any more', function (): void {
    Permission::query()->create(['name' => 'legacy.thing.view', 'guard_name' => 'web']);

    artisan('permissions:sync')->assertSuccessful();
    expect(Permission::query()->where('name', 'legacy.thing.view')->exists())->toBeTrue();

    artisan('permissions:sync', ['--prune' => true])->expectsOutputToContain('1 deleted')->assertSuccessful();
    expect(Permission::query()->where('name', 'legacy.thing.view')->exists())->toBeFalse();
});

it('gives a tenant created with tenant:create its default roles', function (): void {
    artisan('tenant:create', ['slug' => 'lakeside', 'name' => 'Lakeside Retreat'])->assertSuccessful();

    expect(rolePermissions(tenant('lakeside'), 'tenant-owner'))->toBe(app(PermissionRegistry::class)->names());
});

it('keeps role permissions separate per tenant, including the permission cache', function (): void {
    [$a, $b] = [withDefaultRoles(Tenant::factory()->create(['slug' => 'tenant-a'])), withDefaultRoles(Tenant::factory()->create(['slug' => 'tenant-b']))];
    $context = app(TenantContext::class);

    $userA = $context->run($a, function (): User {
        Role::query()->create(['name' => 'Night manager', 'guard_name' => 'web'])->givePermissionTo('iam.user.view');

        return tenantUser(tenant('tenant-a'))->assignRole('Night manager');
    });
    $userB = $context->run($b, function (): User {
        Role::query()->create(['name' => 'Night manager', 'guard_name' => 'web'])->givePermissionTo('iam.role.view');

        return tenantUser(tenant('tenant-b'))->assignRole('Night manager');
    });

    foreach (range(1, 2) as $round) {
        $context->run($a, fn () => expect([$userA->fresh()?->can('iam.user.view'), $userA->fresh()?->can('iam.role.view')])->toBe([true, false]));
        $context->run($b, fn () => expect([$userB->fresh()?->can('iam.user.view'), $userB->fresh()?->can('iam.role.view')])->toBe([false, true]));
    }
});
