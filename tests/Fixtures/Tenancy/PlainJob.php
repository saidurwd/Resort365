<?php

namespace Tests\Fixtures\Tenancy;

use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * A job that is not tenant-aware.
 */
class PlainJob implements ShouldQueue
{
    use Queueable;

    public function handle(TenantContext $context): void
    {
        Cache::put('plain.has_tenant', $context->check());
    }
}
