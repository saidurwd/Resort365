<?php

use App\Http\Middleware\IdentifyTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
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
        // TODO(step-0.5): add EnsureUserBelongsToTenant after authentication.
        $middleware->group('tenant', [
            IdentifyTenant::class,
        ]);

        // The tenant must be known before route-model binding loads tenant-owned models.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: IdentifyTenant::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
