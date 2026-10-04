<?php

namespace Modules\Restaurant\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Restaurant';

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
     * Web routes run on tenant subdomains with the `tenant` middleware group
     * (ARCHITECTURE §4.2) and return 403 when the module is disabled for the tenant (§4.3). A central module (e.g. Platform) maps its routes to
     * config('tenancy.central_domain') instead.
     */
    protected function mapWebRoutes(): void
    {
        Route::domain('{tenant}.'.config('tenancy.central_domain'))
            ->middleware(['web', 'tenant', 'module:'.strtolower($this->name)])
            ->group(module_path($this->name, '/routes/web.php'));
    }

    /**
     * API routes: stateless, on tenant subdomains under /api.
     */
    protected function mapApiRoutes(): void
    {
        Route::domain('{tenant}.'.config('tenancy.central_domain'))
            ->middleware(['api', 'tenant', 'module:'.strtolower($this->name)])
            ->prefix('api')
            ->name('api.')
            ->group(module_path($this->name, '/routes/api.php'));
    }
}
