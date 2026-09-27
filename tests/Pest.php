<?php

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\View;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Testing\TestView;
use Illuminate\View\FileViewFinder;
use Modules\IAM\Actions\SeedDefaultRoles;
use Modules\IAM\Actions\SyncPermissions;
use Modules\IAM\Models\Role;
use Modules\IAM\Models\User;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature and tenancy tests (application and modules) boot the Laravel application.
| Unit and architecture tests stay framework-free unless a test file opts
| in with `uses(TestCase::class)`.
|
*/

pest()->extend(TestCase::class)->in('Feature', 'Tenancy', '../Modules/*/tests/Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
|
| Function versions of Laravel's view-testing helpers, so tests stay
| readable and fully typed for Larastan (Pest closures don't expose $this).
|
*/

/**
 * Render a Blade string (same as Laravel's InteractsWithViews::blade()).
 *
 * @param  array<string, mixed>  $data
 */
function blade(string $template, array $data = []): TestView
{
    $directory = sys_get_temp_dir();
    $finder = View::getFinder();

    if ($finder instanceof FileViewFinder && ! in_array($directory, $finder->getPaths(), true)) {
        $finder->addLocation($directory);
    }

    $name = pathinfo((string) tempnam($directory, 'laravel-blade'), PATHINFO_FILENAME);
    file_put_contents($directory.'/'.$name.'.blade.php', $template);

    return new TestView(view($name, $data));
}

/**
 * Share validation errors with every view (same as InteractsWithViews::withViewErrors()).
 *
 * @param  array<string, string|list<string>>  $errors
 */
function withViewErrors(array $errors, string $bag = 'default'): void
{
    View::share('errors', (new ViewErrorBag)->put($bag, new MessageBag($errors)));
}

/**
 * Absolute URL on a tenant subdomain, e.g. tenantUrl('sunrise', '/login').
 */
function tenantUrl(string $slug, string $path = '/'): string
{
    return 'http://'.$slug.'.'.config('tenancy.central_domain').$path;
}

/**
 * Absolute URL on the central domain.
 */
function centralUrl(string $path = '/'): string
{
    return 'http://'.config('tenancy.central_domain').$path;
}

/**
 * Create a user in the given tenant (factory password: "Password123").
 *
 * @param  array<string, mixed>  $attributes
 */
function tenantUser(Tenant $tenant, array $attributes = [], ?Closure $state = null): User
{
    return app(TenantContext::class)->run($tenant, function () use ($attributes, $state): User {
        $factory = User::factory();

        return ($state instanceof Closure ? $state($factory) : $factory)->create($attributes);
    });
}

/**
 * A tenant created earlier in the test, by slug.
 */
function tenant(string $slug): Tenant
{
    return Tenant::query()->where('slug', $slug)->firstOrFail();
}

/**
 * A user of the given tenant, by email.
 */
function userIn(string $tenantSlug, string $email): User
{
    return app(TenantContext::class)->run(
        tenant($tenantSlug),
        fn (): User => User::query()->where('email', $email)->firstOrFail(),
    );
}

/**
 * Store registered permissions and create the tenant's default roles (as for a new tenant).
 */
function withDefaultRoles(Tenant $tenant): Tenant
{
    SyncPermissions::make()->handle();
    app(TenantContext::class)->run($tenant, function (): void {
        SeedDefaultRoles::make()->handle();
    });

    return $tenant;
}

/**
 * A user of the tenant holding one default role (call withDefaultRoles() first).
 *
 * @param  array<string, mixed>  $attributes
 */
function tenantUserAs(Tenant $tenant, DefaultRole $role, array $attributes = []): User
{
    $user = tenantUser($tenant, $attributes);
    app(TenantContext::class)->run($tenant, fn (): User => $user->assignRole($role->value));

    return $user;
}

/**
 * Id of a default role in the tenant.
 */
function roleId(Tenant $tenant, DefaultRole $role): int
{
    return app(TenantContext::class)->run(
        $tenant,
        fn (): int => Role::query()->where('name', $role->value)->value('id') ?? throw new RuntimeException("Role {$role->value} missing"),
    );
}
