<?php

use App\Http\Middleware\EnsureModuleEnabled;
use App\Http\Middleware\EnsureUserBelongsToTenant;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetCurrentProperty;
use App\Support\Tenancy\PlanLimitReached;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Sentry\Laravel\Integration;
use Sentry\State\Scope;
use Symfony\Component\HttpFoundation\Response;

use function Sentry\configureScope;

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

        // Security headers on every response, the central domain's and the tenants' (Step 7.5).
        $middleware->append(SecurityHeaders::class);

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

        // Everything under /platform (the console, and Horizon and Pulse since Step 7.5) belongs to the platform admins' sign-in.
        $middleware->redirectGuestsTo(fn (Request $request): string => $request->routeIs('platform.*') || $request->is('platform', 'platform/*') ? route('platform.login') : route('login'));
        $middleware->redirectUsersTo(fn (Request $request): string => $request->routeIs('platform.*') || $request->is('platform', 'platform/*') ? route('platform.console') : route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Sentry (Step 7.5): which tenant and which user id, never names or emails (send_default_pii is off).
        $exceptions->reportable(function (Throwable $exception): void {
            if (app()->bound('sentry')) {
                configureScope(function (Scope $scope): void {
                    $scope->setTag('tenant', (string) (app(TenantContext::class)->tenant()->slug ?? 'central'));
                    $id = auth()->id();
                    $scope->setUser($id !== null ? ['id' => (string) $id] : []);
                });
            }
        });
        Integration::handles($exceptions);

        // A plan limit is a friendly refusal, on the page the person was on (Step 7.3).
        $exceptions->render(function (PlanLimitReached $exception, Request $request): ?Response {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => $exception->getMessage(), 'limit' => $exception->limit], 422);
            }

            return $request->hasSession() ? back()->withInput()->with('error', $exception->getMessage()) : null;
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
