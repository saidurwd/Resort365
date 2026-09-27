<?php

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('serves two tenants on their own subdomains', function (): void {
    Tenant::factory()->create(['slug' => 'sunrise', 'name' => 'Sunrise Resorts Ltd']);
    Tenant::factory()->create(['slug' => 'greenvalley', 'name' => 'Green Valley Resort']);

    get(tenantUrl('sunrise', '/login'))->assertOk()->assertSee('Sunrise Resorts Ltd')->assertDontSee('Green Valley Resort');
    get(tenantUrl('greenvalley', '/login'))->assertOk()->assertSee('Green Valley Resort')->assertDontSee('Sunrise Resorts Ltd');
});

it('matches the subdomain case-insensitively', function (): void {
    Tenant::factory()->create(['slug' => 'sunrise', 'name' => 'Sunrise Resorts Ltd']);

    get(tenantUrl('SunRise', '/login'))->assertOk()->assertSee('Sunrise Resorts Ltd');
});

it('returns 404 for an unknown subdomain', function (): void {
    get(tenantUrl('nosuchresort'))->assertNotFound();
});

it('returns 404 for a cancelled tenant', function (): void {
    Tenant::factory()->cancelled()->create(['slug' => 'oldco']);

    get(tenantUrl('oldco'))->assertNotFound();
});

it('shows the suspended page for a suspended tenant', function (): void {
    Tenant::factory()->suspended()->create(['slug' => 'lakeside', 'name' => 'Lakeside Retreat']);

    get(tenantUrl('lakeside'))
        ->assertForbidden()
        ->assertSee(__('This account is suspended'))
        ->assertSee('Lakeside Retreat');
});

it('lets trial tenants in', function (): void {
    Tenant::factory()->trial()->create(['slug' => 'trialco', 'name' => 'Trial Co']);

    get(tenantUrl('trialco', '/login'))->assertOk()->assertSee('Trial Co');
});

it('keeps the central domain separate from tenant routes', function (): void {
    Tenant::factory()->create(['slug' => 'sunrise']);

    get('http://'.config('tenancy.central_domain').'/')->assertOk()->assertDontSee('Welcome to');
    get(tenantUrl('sunrise', '/ui-kit'))->assertNotFound();
});

it('sets the tenant context and hides the {tenant} parameter from controllers', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'sunrise']);

    Route::domain('{tenant}.'.config('tenancy.central_domain'))
        ->middleware(['web', 'tenant'])
        ->get('/_probe/{id}', fn (TenantContext $context, string $id): array => [
            'tenant' => $context->id(),
            'id' => $id,
            'parameters' => array_keys(request()->route()?->parameters() ?? []),
            'url' => route('tenant.home'),
        ]);

    get(tenantUrl('sunrise', '/_probe/42'))->assertOk()->assertExactJson([
        'tenant' => $tenant->id,
        'id' => '42',
        'parameters' => ['id'],
        'url' => rtrim(tenantUrl('sunrise'), '/'),
    ]);
});
