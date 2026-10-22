<?php

/*
| Security headers, health check, the monitoring dashboards and the shared rate limits (Step 7.5, ARCHITECTURE §9.1).
*/
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Platform\Models\PlatformAdmin;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('sends the security headers, with a nonce the page\'s own scripts carry', function (): void {
    $response = get(centralUrl('/signup'))->assertOk();
    $policy = (string) $response->headers->get('Content-Security-Policy');
    preg_match("/'nonce-([^']+)'/", $policy, $nonce);

    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN')
        ->and($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin')
        ->and($response->headers->has('Strict-Transport-Security'))->toBeFalse()
        ->and($policy)->toContain("default-src 'self'")->toContain("frame-ancestors 'self'")->toContain("object-src 'none'")
        ->and($policy)->toContain('form-action \'self\' http://'.config('tenancy.central_domain').' http://*.'.config('tenancy.central_domain'))
        ->and($nonce[1] ?? '')->not->toBe('');
    $response->assertSeeHtml('nonce="'.$nonce[1].'"');
});

it('answers the health check', function (): void {
    get(centralUrl('/up'))->assertOk();
});

it('shows Horizon and Pulse to platform admins only', function (): void {
    get(centralUrl('/platform/horizon'))->assertRedirect(centralUrl('/platform/login'));
    get(centralUrl('/platform/pulse'))->assertRedirect(centralUrl('/platform/login'));

    actingAs(tenantUserAs(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])), DefaultRole::TenantOwner));
    get(centralUrl('/platform/horizon'))->assertRedirect(centralUrl('/platform/login'));

    actingAs(PlatformAdmin::factory()->create(), 'platform');
    get(centralUrl('/platform/horizon'))->assertOk();
    expect(Gate::forUser(null)->allows('viewPulse'))->toBeTrue();
});

it('limits report screens per person and tenant', function (): void {
    $tenant = withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise']));
    actingAs(tenantUserAs($tenant, DefaultRole::GeneralManager));
    RateLimiter::clear('reports');

    for ($i = 0; $i < 60; $i++) {
        get(tenantUrl('sunrise', '/reports/forecast'));
    }

    get(tenantUrl('sunrise', '/reports/forecast'))->assertTooManyRequests();
});
