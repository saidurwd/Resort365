<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Horizon (at /platform/horizon on the central domain, Step 7.5) is for signed-in platform admins only; the route middleware
     * already requires the `platform` guard.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn (mixed $user = null): bool => Auth::guard('platform')->check());
    }
}
