<?php

namespace Tests\Fixtures\Tenancy;

use App\Support\Tenancy\InteractsWithTenant;
use App\Support\Tenancy\TenantAware;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Records which tenant it ran as, and what it could see of the tenant-owned probe table.
 */
class RecordTenantJob implements ShouldQueue, TenantAware
{
    use InteractsWithTenant;
    use Queueable;

    public function handle(TenantContext $context): void
    {
        Cache::put('job.tenant_id', $context->id());
        Cache::put('job.probe_codes', IsolationProbe::query()->orderBy('code')->pluck('code')->all());
    }
}
