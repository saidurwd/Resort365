<?php

namespace App\Support\Tenancy;

use App\Models\Tenant;
use Closure;
use Illuminate\Support\Facades\Context;

/**
 * Job middleware: runs the job as the tenant that dispatched it.
 */
class RestoreTenantContext
{
    public function handle(object $job, Closure $next): mixed
    {
        $tenantId = Context::getHidden(TenantContext::CONTEXT_KEY);

        if ($tenantId === null) {
            throw new TenantContextMissing('The tenant-aware job ['.$job::class.'] was dispatched without a current tenant.');
        }

        $tenant = Tenant::query()->findOrFail($tenantId);

        return app(TenantContext::class)->run($tenant, fn (): mixed => $next($job));
    }
}
