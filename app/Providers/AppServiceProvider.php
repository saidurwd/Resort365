<?php

namespace App\Providers;

use App\Support\Authorization\PermissionRegistry;
use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
use App\Support\Tenancy\DisplayTimezone;
use App\Support\Tenancy\ModuleAccess;
use App\Support\Tenancy\PropertyContext;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One tenant per request / queued job; reset between them.
        $this->app->scoped(TenantContext::class);
        $this->app->scoped(ModuleAccess::class);
        $this->app->scoped(PropertyContext::class);
        $this->app->scoped(DisplayTimezone::class);

        // Filled by modules' service providers at boot.
        $this->app->singleton(PermissionRegistry::class);
        $this->app->singleton(MenuRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Hardening (Step 7.5): production never runs with debug pages, and lazy loading (an N+1 query) fails loudly everywhere else.
        if ($this->app->isProduction() && config('app.debug')) {
            throw new RuntimeException('APP_DEBUG must be false in production.');
        }

        Model::preventLazyLoading(! $this->app->isProduction());

        $this->registerRateLimiters();

        // /up (uptime checks, Step 7.5) fails when the database or the cache store (Redis in production) does not answer.
        Event::listen(DiagnosingHealth::class, function (): void {
            DB::select('select 1');
            Cache::store()->put('health:ping', now()->timestamp, 60);
        });

        // Pulse (/platform/pulse) is for signed-in platform admins only, like Horizon.
        Gate::define('viewPulse', fn (mixed $user = null): bool => Auth::guard('platform')->check());

        // Page layouts live in resources/views/layouts (ARCHITECTURE §11): <x-layouts::app>, <x-layouts::guest>, <x-layouts::print>.
        Blade::anonymousComponentPath(resource_path('views/layouts'), 'layouts');

        // Stored timestamps are UTC; show them in the property's timezone (ARCHITECTURE §12 rule 9):
        // {{ $order->opened_at->inPropertyTime()->format('H:i') }}. See DisplayTimezone.
        foreach ([Carbon::class, CarbonImmutable::class] as $carbon) {
            $carbon::macro('inPropertyTime', fn (): CarbonInterface => app(DisplayTimezone::class)->convert($this));
        }

        // Password policy (ARCHITECTURE §9.1). Breach checks call an external API, so production only.
        Password::defaults(fn (): Password => app()->isProduction()
            ? Password::min(10)->mixedCase()->numbers()->uncompromised()
            : Password::min(10)->mixedCase()->numbers());

        $this->registerMenu($this->app->make(MenuRegistry::class));

        View::composer('layouts.partials.sidebar', function (\Illuminate\View\View $view): void {
            $view->with('menu', $this->app->make(MenuRegistry::class)->forUser(auth('web')->user()));
        });
    }

    /**
     * Shared rate limits (Step 7.5), per tenant: `reports` for report screens and exports, which can start heavy work; `device-api`
     * for the POS and kitchen display's JSON calls, per device and sign-in session (they poll every few seconds).
     */
    private function registerRateLimiters(): void
    {
        $who = fn (Request $request): string => (app(TenantContext::class)->id() ?? 0).'|'.($request->user()?->getAuthIdentifier() ?? $request->ip());

        RateLimiter::for('reports', fn (Request $request): Limit => Limit::perMinute(60)->by('reports|'.$who($request)));
        RateLimiter::for('device-api', fn (Request $request): Limit => Limit::perMinute(600)->by('device|'.($request->cookie('pos_device') ?? $request->cookie('kds_device') ?? $who($request)).'|'.($request->hasSession() ? $request->session()->getId() : '')));
    }

    /**
     * The application shell's own sidebar entries; modules add theirs in their providers.
     */
    private function registerMenu(MenuRegistry $menu): void
    {
        $onTenant = fn (): bool => app(TenantContext::class)->check();

        $menu->add(new MenuItem('dashboard', 'Dashboard', 'bi-speedometer2', route: 'dashboard', order: 0,
            visible: fn (): bool => $onTenant() && auth('web')->check()));
        $menu->add(new MenuItem('home', 'Home', 'bi-house', route: 'central.home', order: 0,
            visible: fn (): bool => ! $onTenant()));

        $menu->group('ui-kit', 'UI Kit', 'bi-palette', order: 9999);
        $menu->add(new MenuItem('ui-kit.components', 'Components', route: 'ui-kit.index', parent: 'ui-kit', order: 10,
            visible: fn (): bool => app()->isLocal()));
        $menu->add(new MenuItem('ui-kit.print', 'Print layout', route: 'ui-kit.print', parent: 'ui-kit', order: 20,
            visible: fn (): bool => app()->isLocal()));
    }
}
