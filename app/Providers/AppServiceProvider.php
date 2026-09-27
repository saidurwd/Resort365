<?php

namespace App\Providers;

use App\Support\Tenancy\TenantContext;
use App\Support\Ui\SidebarMenu;
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

        View::composer('layouts.partials.sidebar', function (\Illuminate\View\View $view): void {
            $view->with('menu', SidebarMenu::items());
        });
    }
}
