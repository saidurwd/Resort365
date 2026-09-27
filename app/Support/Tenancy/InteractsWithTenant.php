<?php

namespace App\Support\Tenancy;

/**
 * For jobs implementing TenantAware. The tenant id captured at dispatch travels in
 * Laravel's hidden Context; RestoreTenantContext sets it again before handle().
 *
 * If the job defines its own middleware(), merge in `new RestoreTenantContext`.
 *
 * Dispatch while the tenant is current. `Job::dispatch()` queues the job when the
 * returned PendingDispatch is destroyed, so inside TenantContext::run() dispatch as a
 * statement, not as the callback's return value (`fn () => Job::dispatch()` would
 * queue it after run() has restored the previous tenant, and the job then fails).
 */
trait InteractsWithTenant
{
    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RestoreTenantContext];
    }
}
