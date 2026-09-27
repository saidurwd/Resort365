<?php

namespace App\Providers;

use App\Support\Tenancy\TenantContext;
use App\Support\Ui\SidebarMenu;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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

        View::composer('layouts.partials.sidebar', function (\Illuminate\View\View $view): void {
            $view->with('menu', SidebarMenu::items());
        });
    }
}
