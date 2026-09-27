<?php

use App\Models\Tenant;
use App\Models\TenantModule;
use App\Support\Tenancy\ModuleAccess;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Route::domain('{tenant}.'.config('tenancy.central_domain'))->middleware(['web', 'tenant', 'module:frontoffice'])
        ->get('/_frontoffice', fn (): string => 'front office');
});

it('enables modules by default', function (): void {
    Tenant::factory()->create(['slug' => 'sunrise']);

    get(tenantUrl('sunrise', '/_frontoffice'))->assertOk();
});

it('returns 403 for a module disabled for the tenant, only for that tenant', function (): void {
    $sunrise = Tenant::factory()->create(['slug' => 'sunrise']);
    Tenant::factory()->create(['slug' => 'greenvalley']);
    app(TenantContext::class)->run($sunrise, fn () => TenantModule::factory()->create(['module' => 'frontoffice', 'enabled' => false]));

    get(tenantUrl('sunrise', '/_frontoffice'))->assertForbidden()->assertSee(__('This module is not enabled for your account.'));
    get(tenantUrl('greenvalley', '/_frontoffice'))->assertOk();
});

it('always enables the core modules', function (string $module): void {
    $tenant = Tenant::factory()->create();

    app(TenantContext::class)->run($tenant, function () use ($module): void {
        TenantModule::factory()->create(['module' => $module, 'enabled' => false]);
        app(ModuleAccess::class)->flush();

        expect(app(ModuleAccess::class)->enabled($module))->toBeTrue();
    });
})->with(['core', 'iam', 'platform']);

it('adds the module middleware to module routes', function (): void {
    expect(Route::getRoutes()->getByName('iam.users.index')?->gatherMiddleware())->toContain('module:iam');
});
