<?php

namespace App\Providers;

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
        //
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
