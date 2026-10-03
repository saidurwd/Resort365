<?php

use App\Http\Middleware\EnsureModuleEnabled;
use App\Http\Middleware\EnsureUserBelongsToTenant;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\SetCurrentProperty;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            $central = config('tenancy.central_domain');

            // Central domain: marketing, sign-up, platform console, developer tools.
            Route::domain($central)->middleware('web')->group(base_path('routes/web.php'));

            // Tenant subdomains: the resort application. Modules register their routes the same way.
            Route::domain('{tenant}.'.$central)->middleware(['web', 'tenant'])->group(base_path('routes/tenant.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['module' => EnsureModuleEnabled::class]);

        // Modules append to this group (e.g. IAM adds EnsureUserIsActive and SetUserLocale).
        $middleware->group('tenant', [
            IdentifyTenant::class,
            EnsureUserBelongsToTenant::class,
            SetCurrentProperty::class,
        ]);

        // The tenant must be known before authentication, throttling and route-model binding
        // touch tenant-owned models (they sort after AuthenticatesRequests in Laravel's priority list).
        // (Laravel applies priority appends before prepends, so anchor both to AuthenticatesRequests.)
        $middleware->prependToPriorityList(before: AuthenticatesRequests::class, prepend: IdentifyTenant::class);
        $middleware->prependToPriorityList(before: AuthenticatesRequests::class, prepend: EnsureUserBelongsToTenant::class);
        $middleware->prependToPriorityList(before: AuthenticatesRequests::class, prepend: SetCurrentProperty::class);

        $middleware->redirectGuestsTo(fn (Request $request): string => $request->routeIs('platform.*') ? route('platform.login') : route('login'));
        $middleware->redirectUsersTo(fn (Request $request): string => $request->routeIs('platform.*') ? route('platform.console') : route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
