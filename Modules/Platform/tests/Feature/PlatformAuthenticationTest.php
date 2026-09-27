<?php

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Platform\Models\PlatformAdmin;

use function Pest\Laravel\artisan;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    PlatformAdmin::factory()->create(['name' => 'Platform Admin', 'email' => 'admin@resort365.test']);
});

function platformAdmin(): PlatformAdmin
{
    return PlatformAdmin::query()->where('email', 'admin@resort365.test')->firstOrFail();
}

it('signs a platform admin in on the central domain', function (): void {
    get(centralUrl('/platform/login'))->assertOk()->assertSee(__('Platform administration'));

    post(centralUrl('/platform/login'), ['email' => 'Admin@Resort365.test', 'password' => 'Password123'])
        ->assertRedirect(centralUrl('/platform'));

    assertAuthenticatedAs(platformAdmin(), 'platform');
    assertGuest('web');
    get(centralUrl('/platform'))->assertOk()->assertSee('admin@resort365.test');

    expect(platformAdmin()->fresh()?->last_login_at)->not->toBeNull();
});

it('rejects wrong credentials and tenant users', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'sunrise']);
    tenantUser($tenant, ['email' => 'owner@sunrise.test']);

    post(centralUrl('/platform/login'), ['email' => 'admin@resort365.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
    post(centralUrl('/platform/login'), ['email' => 'owner@sunrise.test', 'password' => 'Password123'])->assertSessionHasErrors('email');

    assertGuest('platform');
});

it('throttles platform sign-in', function (): void {
    foreach (range(1, 5) as $attempt) {
        post(centralUrl('/platform/login'), ['email' => 'admin@resort365.test', 'password' => 'wrong']);
    }

    post(centralUrl('/platform/login'), ['email' => 'admin@resort365.test', 'password' => 'Password123'])->assertTooManyRequests();
});

it('signs out', function (): void {
    post(centralUrl('/platform/login'), ['email' => 'admin@resort365.test', 'password' => 'Password123']);

    post(centralUrl('/platform/logout'))->assertRedirect(centralUrl('/platform/login'));
    assertGuest('platform');
});

it('sends guests to the platform sign-in page', function (): void {
    get(centralUrl('/platform'))->assertRedirect(centralUrl('/platform/login'));
});

it('keeps platform and tenant sign-in on their own domains', function (): void {
    Tenant::factory()->create(['slug' => 'sunrise']);

    get(tenantUrl('sunrise', '/platform/login'))->assertNotFound();
    get(centralUrl('/login'))->assertNotFound();
});

it('creates a platform admin from the command line', function (): void {
    artisan('platform:create-admin', ['email' => 'ops@resort365.test', 'name' => 'Ops'])
        ->expectsQuestion('Password', 'Operations123')
        ->assertSuccessful();

    assertDatabaseHas('platform_admins', ['email' => 'ops@resort365.test', 'name' => 'Ops']);

    artisan('platform:create-admin', ['email' => 'weak@resort365.test', 'name' => 'Weak'])
        ->expectsQuestion('Password', 'short')
        ->assertFailed();
});
