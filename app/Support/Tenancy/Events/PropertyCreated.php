<?php

namespace App\Support\Tenancy\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A property (resort) was created in the current tenant. Modules create their per-property
 * defaults (e.g. Core's document sequences). Carries ids only, so any module may listen.
 */
class PropertyCreated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $propertyId,
    ) {}
}
