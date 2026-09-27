<?php

namespace Modules\IAM\Listeners;

use App\Support\Tenancy\Events\TenantSwitched;
use Spatie\Permission\PermissionRegistrar;

/**
 * spatie/laravel-permission caches permissions together with their roles. Roles belong to
 * a tenant, so each tenant gets its own cache key; switching tenants reloads from it.
 */
class UseTenantPermissionCache
{
    public const string KEY_PREFIX = 'spatie.permission.cache.tenant.';

    public function __construct(private readonly PermissionRegistrar $registrar) {}

    public function handle(TenantSwitched $event): void
    {
        config(['permission.cache.key' => self::KEY_PREFIX.($event->tenant->id ?? 'none')]);

        $this->registrar->initializeCache();
    }
}
