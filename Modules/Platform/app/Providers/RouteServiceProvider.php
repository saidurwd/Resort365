<?php

namespace Modules\Platform\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Platform';

    /**
     * Called before routes are registered.
     *
     * Register any model bindings or pattern based filters.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Define the routes for the application.
     */
    public function map(): void
    {
        $this->mapApiRoutes();
        $this->mapWebRoutes();
    }

    /**
     * Platform is a central module: its routes live on the central domain only.
     */
    protected function mapWebRoutes(): void
    {
        Route::domain(config('tenancy.central_domain'))
            ->middleware('web')
            ->group(module_path($this->name, '/routes/web.php'));
    }

    /**
     * Platform API (central domain). TODO(step-8.1): platform API endpoints.
     */
    protected function mapApiRoutes(): void
    {
        Route::domain(config('tenancy.central_domain'))
            ->middleware('api')
            ->prefix('api')
            ->name('api.')
            ->group(module_path($this->name, '/routes/api.php'));
    }
}
