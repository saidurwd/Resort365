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
        ->and(rolePermissions($a, 'auditor'))->toBe([
            'billing.charge-code.view', 'billing.city-ledger.view', 'billing.extra-service.view', 'billing.folio.view', 'billing.invoice.view', 'billing.payment.view', 'billing.shift.view', 'core.audit.view', 'core.notification-template.view', 'core.sequence.view', 'core.setting.view', 'core.tax.view', 'frontoffice.audit.view', 'frontoffice.desk.view', 'frontoffice.report.view', 'guest.company.view', 'guest.guest.view', 'guest.travel-agent.view', 'housekeeping.board.view', 'housekeeping.lost-found.view', 'housekeeping.work-order.view', 'iam.role.view', 'iam.user.view', 'property.amenity.view', 'property.cottage.view', 'property.department.view', 'property.property.access-all', 'property.property.view', 'property.room.view', 'rates.policy.view', 'rates.promotion.view', 'rates.rate-plan.view', 'rates.rate.view', 'rates.season.view', 'reservation.availability.view', 'reservation.booking.view', 'reservation.quote.view', 'reservation.report.view', 'restaurant.menu.view', 'restaurant.outlet.view', 'restaurant.session.view',
        ])
        ->and(rolePermissions($a, 'general-manager'))->toBe([
            'billing.charge-code.manage', 'billing.charge-code.view', 'billing.city-ledger.manage', 'billing.city-ledger.view', 'billing.credit-note.issue', 'billing.extra-service.manage', 'billing.extra-service.view', 'billing.folio.adjust', 'billing.folio.post', 'billing.folio.view', 'billing.folio.void', 'billing.invoice.view', 'billing.payment.create', 'billing.payment.view', 'billing.refund.issue', 'billing.shift.view', 'core.audit.view', 'core.notification-template.manage', 'core.notification-template.view', 'core.sequence.update', 'core.sequence.view', 'core.setting.update', 'core.setting.view', 'core.tax.manage', 'core.tax.view', 'frontoffice.audit.run', 'frontoffice.audit.view', 'frontoffice.checkin.perform', 'frontoffice.checkout.perform', 'frontoffice.desk.view', 'frontoffice.report.view', 'frontoffice.stay.change', 'frontoffice.stay.reprice', 'guest.company.manage', 'guest.company.view', 'guest.guest.blacklist', 'guest.guest.create', 'guest.guest.merge', 'guest.guest.update', 'guest.guest.view', 'guest.guest.view-id', 'guest.travel-agent.manage', 'guest.travel-agent.view', 'housekeeping.block.manage', 'housekeeping.board.view', 'housekeeping.lost-found.manage', 'housekeeping.lost-found.view', 'housekeeping.schedule.manage', 'housekeeping.work-order.create', 'housekeeping.work-order.manage', 'housekeeping.work-order.view', 'iam.role.view', 'iam.user.invite', 'iam.user.update', 'iam.user.view', 'property.amenity.manage', 'property.amenity.view', 'property.cottage.manage', 'property.cottage.view', 'property.department.manage', 'property.department.view', 'property.property.update', 'property.property.view', 'property.room.manage', 'property.room.view', 'rates.policy.manage', 'rates.policy.view', 'rates.promotion.manage', 'rates.promotion.view', 'rates.rate-plan.manage', 'rates.rate-plan.view', 'rates.rate.manage', 'rates.rate.view', 'rates.season.manage', 'rates.season.view', 'reservation.availability.view', 'reservation.booking.cancel', 'reservation.booking.create', 'reservation.booking.update', 'reservation.booking.view', 'reservation.deposit.override', 'reservation.quote.create', 'reservation.quote.view', 'reservation.report.view', 'restaurant.access.manage', 'restaurant.floor-plan.manage', 'restaurant.kds.use', 'restaurant.menu.manage', 'restaurant.menu.view', 'restaurant.order.open-item', 'restaurant.order.take', 'restaurant.order.void', 'restaurant.outlet.access-all', 'restaurant.outlet.manage', 'restaurant.outlet.view', 'restaurant.pos.use', 'restaurant.price.manage', 'restaurant.session.approve-variance', 'restaurant.session.manage', 'restaurant.session.view',
        ])
        ->and(rolePermissions($a, 'front-desk-agent'))->toBe([
            'billing.folio.post', 'billing.folio.view', 'billing.invoice.view', 'billing.payment.create', 'billing.payment.view', 'billing.shift.open', 'frontoffice.checkin.perform', 'frontoffice.checkout.perform', 'frontoffice.desk.view', 'frontoffice.stay.change', 'guest.company.view', 'guest.guest.create', 'guest.guest.update', 'guest.guest.view', 'guest.guest.view-id', 'guest.travel-agent.view', 'housekeeping.board.view', 'housekeeping.lost-found.manage', 'housekeeping.lost-found.view', 'housekeeping.work-order.create', 'property.cottage.view', 'property.room.view', 'rates.policy.view', 'rates.promotion.view', 'rates.rate-plan.view', 'rates.rate.view', 'rates.season.view', 'reservation.availability.view', 'reservation.booking.cancel', 'reservation.booking.create', 'reservation.booking.update', 'reservation.booking.view', 'reservation.quote.create', 'reservation.quote.view',
        ]);
});

it('updates default roles when a module adds a permission, and leaves custom roles alone', function (): void {
    $tenant = Tenant::factory()->create();
    artisan('permissions:sync');
    app(TenantContext::class)->run($tenant, fn () => Role::query()->create(['name' => 'Night manager', 'guard_name' => 'web'])->givePermissionTo('iam.user.view'));

    app(PermissionRegistry::class)->register('Lost and found', [
        new PermissionDefinition('lostfound.item.view', 'View lost and found', [DefaultRole::FrontDeskAgent]),
    ]);
    artisan('permissions:sync')->assertSuccessful();

    expect(rolePermissions($tenant, 'front-desk-agent'))->toBe([
        'billing.folio.post', 'billing.folio.view', 'billing.invoice.view', 'billing.payment.create', 'billing.payment.view', 'billing.shift.open', 'frontoffice.checkin.perform', 'frontoffice.checkout.perform', 'frontoffice.desk.view', 'frontoffice.stay.change', 'guest.company.view', 'guest.guest.create', 'guest.guest.update', 'guest.guest.view', 'guest.guest.view-id', 'guest.travel-agent.view', 'housekeeping.board.view', 'housekeeping.lost-found.manage', 'housekeeping.lost-found.view', 'housekeeping.work-order.create', 'lostfound.item.view', 'property.cottage.view', 'property.room.view', 'rates.policy.view', 'rates.promotion.view', 'rates.rate-plan.view', 'rates.rate.view', 'rates.season.view', 'reservation.availability.view', 'reservation.booking.cancel', 'reservation.booking.create', 'reservation.booking.update', 'reservation.booking.view', 'reservation.quote.create', 'reservation.quote.view',
    ])
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
