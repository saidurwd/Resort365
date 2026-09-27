<?php

namespace App\Support\Tenancy;

/**
 * A queued job that runs as the tenant that dispatched it.
 * Implement this interface and use the InteractsWithTenant trait.
 */
interface TenantAware
{
    /**
     * @return array<int, object>
     */
    public function middleware(): array;
}
