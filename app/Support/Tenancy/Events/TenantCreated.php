<?php

namespace App\Support\Tenancy\Events;

use App\Models\Tenant;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A tenant was created. Modules seed their per-tenant defaults (e.g. IAM's default roles).
 */
class TenantCreated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Tenant $tenant) {}
}
