<?php

namespace App\Providers;

use App\Support\Authorization\PermissionRegistry;
use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
use App\Support\Tenancy\ModuleAccess;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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

        // Filled by modules' service providers at boot.
        $this->app->singleton(PermissionRegistry::class);
        $this->app->singleton(MenuRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Page layouts live in resources/views/layouts (ARCHITECTURE §11): <x-layouts::app>, <x-layouts::guest>, <x-layouts::print>.
        Blade::anonymousComponentPath(resource_path('views/layouts'), 'layouts');

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
