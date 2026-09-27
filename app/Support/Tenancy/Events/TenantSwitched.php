<?php

namespace App\Support\Tenancy\Events;

use App\Models\Tenant;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The current tenant changed (set, restored or cleared). Tenant-aware caches listen to this.
 */
class TenantSwitched
{
    use Dispatchable;

    public function __construct(public readonly ?Tenant $tenant) {}
}
