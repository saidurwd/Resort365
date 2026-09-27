<?php

namespace Modules\IAM\Providers;

use App\Support\Tenancy\Events\TenantCreated;
use App\Support\Tenancy\Events\TenantSwitched;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\IAM\Listeners\RecordLoginHistory;
use Modules\IAM\Listeners\SeedRolesForNewTenant;
use Modules\IAM\Listeners\UseTenantPermissionCache;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        TenantSwitched::class => [UseTenantPermissionCache::class],
        TenantCreated::class => [SeedRolesForNewTenant::class],
    ];

    /**
     * @var array<int, class-string>
     */
    protected $subscribe = [
        RecordLoginHistory::class,
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = false;

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void {}
}
