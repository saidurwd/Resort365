<?php

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Contracts\Settings;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise']));
});

function requireTwoFactor(bool $required): void
{
    app(TenantContext::class)->run(tenant('sunrise'), fn () => app(Settings::class)->set('iam.require_two_factor', $required));
}

it('does nothing while the setting is off', function (): void {
    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::TenantOwner));

    get(tenantUrl('sunrise', '/dashboard'))->assertOk();
});

it('sends owners and general managers without 2FA to their profile', function (DefaultRole $role): void {
    requireTwoFactor(true);
    actingAs(tenantUserAs(tenant('sunrise'), $role));

    get(tenantUrl('sunrise', '/dashboard'))->assertRedirect(tenantUrl('sunrise', '/iam/profile'))->assertSessionHas('warning');
    get(tenantUrl('sunrise', '/iam/users'))->assertRedirect(tenantUrl('sunrise', '/iam/profile'));
    get(tenantUrl('sunrise', '/iam/profile'))->assertOk();
})->with([DefaultRole::TenantOwner, DefaultRole::GeneralManager]);

it('lets them in once 2FA is on, and never affects other roles', function (): void {
    requireTwoFactor(true);

    $owner = tenantUserAs(tenant('sunrise'), DefaultRole::TenantOwner);
    app(TenantContext::class)->run(tenant('sunrise'), fn () => $owner->forceFill(['two_factor_secret' => encrypt('X'), 'two_factor_confirmed_at' => now()])->save());
    actingAs($owner);
    get(tenantUrl('sunrise', '/dashboard'))->assertOk();

    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::FrontDeskAgent));
    get(tenantUrl('sunrise', '/dashboard'))->assertOk();
});
